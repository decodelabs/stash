<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use DateInterval;
use DecodeLabs\Archetype;
use DecodeLabs\Archetype\NotFoundException as ArchetypeException;
use DecodeLabs\Atlas;
use DecodeLabs\Atlas\Dir;
use DecodeLabs\Coercion;
use DecodeLabs\Dovetail;
use DecodeLabs\Dovetail\Config\Stash as StashConfig;
use DecodeLabs\Exceptional;
use DecodeLabs\Kingdom\ContainerAdapter;
use DecodeLabs\Kingdom\Service;
use DecodeLabs\Kingdom\ServiceTrait;
use DecodeLabs\Monarch;
use DecodeLabs\Stash\Config;
use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\FileStore;
use DecodeLabs\Stash\FileStore\Generic as GenericFileStore;
use DecodeLabs\Stash\Store;
use DecodeLabs\Stash\Store\Generic as GenericStore;
use DecodeLabs\Stash\Item;
use Generator;
use ReflectionClass;
use Stringable;
use Throwable;

class Stash implements Service
{
    use ServiceTrait;

    /**
     * @var list<string>
     */
    protected const array Drivers = [
        'Memcache', 'Redis', 'Apcu', 'Predis', 'PhpFile', 'PhpArray'
    ];

    /**
     * @var array<string,Store<mixed>>
     */
    protected array $caches = [];

    /**
     * @var array<string, FileStore>
     */
    protected array $fileStores = [];

    protected ?Config $config = null;
    protected ?string $defaultPrefix = null;


    public static function provideService(
        ContainerAdapter $container
    ): static {
        if (
            !$container->has(Config::class) &&
            class_exists(Dovetail::class)
        ) {
            $container->setType(Config::class, StashConfig::class);
        }

        return $container->getOrCreate(static::class);
    }

    public function __construct(
        protected(set) Archetype $archetype,
        ?Config $config = null
    ) {
        $this->config = $config;
    }


    /**
     * Set default prefix for all stores
     *
     * @return $this
     */
    public function setDefaultPrefix(
        ?string $prefix
    ): static {
        $this->defaultPrefix = $prefix;
        return $this;
    }

    /**
     * Get default prefix
     */
    public function getDefaultPrefix(): ?string
    {
        return $this->defaultPrefix;
    }



    /**
     * Get cache store by name
     *
     * @return Store<mixed>
     */
    public function load(
        string $namespace
    ): Store {
        if (isset($this->caches[$namespace])) {
            return $this->caches[$namespace];
        }

        $driver = $this->loadDriverFor($namespace);

        $class = $this->archetype->tryResolve(Store::class, $namespace);

        if ($class === null) {
            $class = GenericStore::class;
        }

        $store = new $class($namespace, $driver);

        if ($this->config) {
            // Pile up policy
            if (null !== ($policy = $this->config->getPileUpPolicy($namespace))) {
                $store->setPileUpPolicy($policy);
            }

            // Preempt time
            if (null !== ($time = $this->config->getPreemptTime($namespace))) {
                $store->setPreemptTime($time);
            }

            // Sleep time
            if (null !== ($time = $this->config->getSleepTime($namespace))) {
                $store->setSleepTime($time);
            }

            // Sleep attempts
            if (null !== ($attempts = $this->config->getSleepAttempts($namespace))) {
                $store->setSleepAttempts($attempts);
            }
        }

        $this->caches[$namespace] = $store;
        return $store;
    }

    /**
     * Get cache store without loading config
     *
     * @return Store<mixed>
     */
    public function loadStealth(
        string $namespace
    ): Store {
        if (isset($this->caches[$namespace])) {
            return $this->caches[$namespace];
        }

        $driver = $this->loadDriverFor($namespace, stealth: true);

        try {
            $class = $this->archetype->resolve(Store::class, $namespace);
        } catch (ArchetypeException $e) {
            $class = GenericStore::class;
        }

        return new $class($namespace, $driver);
    }


    /**
     * Get driver for namespace
     */
    public function loadDriverFor(
        string $namespace,
        bool $stealth = false
    ): Driver {
        $drivers = self::Drivers;

        if (
            !$stealth &&
            (null !== ($driverName = $this->config?->getDriverFor($namespace)))
        ) {
            array_unshift($drivers, $driverName);
        }

        foreach ($drivers as $name) {
            try {
                if ($driver = $this->loadDriver($name, $stealth)) {
                    return $driver;
                }
            } catch (ArchetypeException $e) {
                // Ignore
                continue;
            } catch (Throwable $e) {
                Monarch::logException($e);
            }
        }

        throw Exceptional::{'./Stash/ComponentUnavailable'}(
            message: 'No cache drivers available for namespace: ' . $namespace
        );
    }

    /**
     * Load driver by name
     */
    public function loadDriver(
        string $name,
        bool $stealth = false
    ): ?Driver {
        $class = $this->archetype->resolve(Driver::class, $name);

        if (!$stealth) {
            if (
                !$class::isAvailable() ||
                !($this->config?->isDriverEnabled($name) ?? true)
            ) {
                return null;
            }

            $settings = $this->config?->getDriverSettings($name) ?? [];
        } else {
            if (!$class::isAvailable()) {
                return null;
            }

            $settings = [];
        }

        if (
            !isset($settings['prefix']) &&
            $this->defaultPrefix !== null
        ) {
            $settings['prefix'] = $this->defaultPrefix;
        }

        return new $class($this, $settings);
    }


    /**
     * Purge all drivers
     */
    public function purge(): void
    {
        $drivers = ($this->config?->getAllDrivers() ?? []);
        $drivers[] = (new ReflectionClass($this->loadDriverFor('default')))->getShortName();
        $drivers = array_unique($drivers);

        foreach ($drivers as $name) {
            $this->purgeDriver($name);
        }
    }


    /**
     * Purge driver
     */
    public function purgeDriver(
        string $name
    ): void {
        try {
            if (!$driver = $this->loadDriver($name)) {
                return;
            }
        } catch (Throwable $e) {
            Monarch::logException($e);
            return;
        }

        $driver->purge();
    }




    /**
     * Load file store
     */
    public function loadFileStore(
        string $namespace
    ): FileStore {
        if (isset($this->fileStores[$namespace])) {
            return $this->fileStores[$namespace];
        }

        try {
            $class = $this->archetype->resolve(FileStore::class, $namespace);
        } catch (ArchetypeException $e) {
            $class = GenericFileStore::class;
        }

        $settings = $this->config?->getFileStoreSettings($namespace);

        if (
            !isset($settings['prefix']) &&
            $this->defaultPrefix !== null
        ) {
            $settings['prefix'] = $this->defaultPrefix;
        }

        return $this->fileStores[$namespace] = new $class($namespace, $settings);
    }

    /**
     * Prune file stores
     */
    public function pruneFileStores(
        DateInterval|string|Stringable|int $duration
    ): int {
        $count = 0;

        foreach ($this->scanFileStoreDirectories() as $dir) {
            foreach ($dir->scanFiles() as $file) {
                if ($file->hasChangedIn($duration)) {
                    continue;
                }

                $file->delete();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Purge file stores
     */
    public function purgeFileStores(): void
    {
        foreach ($this->scanFileStoreDirectories() as $dir) {
            $dir->delete();
        }
    }

    /**
     * Load all file stores
     *
     * @return Generator<string, Dir>
     */
    protected function scanFileStoreDirectories(): Generator
    {
        $dir = Atlas::getDir(
            Monarch::getPaths()->localData . '/stash/fileStore/'
        );

        $dirs = [];

        if ($dir->exists()) {
            foreach ($dir->scanDirs() as $subDir) {
                $dirs[(string)$subDir] = true;
                yield (string)$subDir => $subDir;
            }
        }

        // Config
        foreach ($this->config?->getAllFileStoreSettings() ?? [] as $name => $settings) {
            if (
                !isset($settings['path']) ||
                empty($settings['path'])
            ) {
                continue;
            }

            $dir = Atlas::getDir(Coercion::asString($settings['path']));

            if (
                $dir->exists() &&
                !isset($dirs[(string)$dir])
            ) {
                yield (string)$dir => $dir;
            }
        }
    }
}
