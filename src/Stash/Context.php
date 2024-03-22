<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use DateInterval;
use DecodeLabs\Archetype;
use DecodeLabs\Archetype\NotFoundException as ArchetypeException;
use DecodeLabs\Atlas;
use DecodeLabs\Atlas\Dir;
use DecodeLabs\Coercion;
use DecodeLabs\Dovetail;
use DecodeLabs\Dovetail\Config\Stash as StashConfig;
use DecodeLabs\Exceptional;
use DecodeLabs\Genesis;
use DecodeLabs\Glitch\Proxy as Glitch;
use DecodeLabs\Stash;
use DecodeLabs\Stash\FileStore\Generic as GenericFileStore;
use DecodeLabs\Stash\Store\Generic as GenericStore;
use DecodeLabs\Veneer;
use Generator;
use ReflectionClass;
use Stringable;
use Throwable;

class Context
{
    public const DRIVERS = [
        'Memcache', 'Redis', 'Apcu', 'Predis', 'PhpFile', 'PhpArray'
    ];

    /**
     * @var array<string, Store<mixed>>
     */
    protected array $caches = [];

    /**
     * @var array<string, FileStore>
     */
    protected array $fileStores = [];

    protected ?Config $config = null;
    protected ?string $defaultPrefix = null;


    /**
     * Set config
     */
    public function setConfig(
        ?Config $config
    ): void {
        $this->config = $config;
    }

    /**
     * Get config
     */
    public function getConfig(): ?Config
    {
        if (
            $this->config === null &&
            class_exists(Dovetail::class)
        ) {
            $this->config = StashConfig::load();
        }

        return $this->config;
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

        try {
            $class = Archetype::resolve(Store::class, $namespace);
        } catch (ArchetypeException $e) {
            $class = GenericStore::class;
        }

        $store = new $class($namespace, $driver);

        if ($config = $this->getConfig()) {
            // Pile up policy
            if (null !== ($policy = $config->getPileUpPolicy($namespace))) {
                $store->setPileUpPolicy($policy);
            }

            // Preempt time
            if (null !== ($time = $config->getPreemptTime($namespace))) {
                $store->setPreemptTime($time);
            }

            // Sleep time
            if (null !== ($time = $config->getSleepTime($namespace))) {
                $store->setSleepTime($time);
            }

            // Sleep attempts
            if (null !== ($attempts = $config->getSleepAttempts($namespace))) {
                $store->setSleepAttempts($attempts);
            }
        }

        $this->caches[$namespace] = $store;
        return $store;
    }

    /**
     * Get driver for namespace
     */
    public function loadDriverFor(
        string $namespace
    ): Driver {
        $drivers = self::DRIVERS;

        if (null !== ($driverName = $this->getConfig()?->getDriverFor($namespace))) {
            array_unshift($drivers, $driverName);
        }

        foreach ($drivers as $name) {
            try {
                if ($driver = $this->loadDriver($name)) {
                    return $driver;
                }
            } catch (ArchetypeException $e) {
                // Ignore
                continue;
            } catch (Throwable $e) {
                Glitch::logException($e);
            }
        }

        throw Exceptional::ComponentUnavailable(
            'No cache drivers available for namespace: ' . $namespace
        );
    }

    /**
     * Load driver by name
     */
    public function loadDriver(
        string $name
    ): ?Driver {
        $class = Archetype::resolve(Driver::class, $name);
        $config = $this->getConfig();

        if (
            !$class::isAvailable() ||
            !($config?->isDriverEnabled($name) ?? true)
        ) {
            return null;
        }

        $settings = $config?->getDriverSettings($name) ?? [];

        if (
            !isset($settings['prefix']) &&
            $this->defaultPrefix !== null
        ) {
            $settings['prefix'] = $this->defaultPrefix;
        }

        return new $class($settings);
    }


    /**
     * Purge all drivers
     */
    public function purge(): void
    {
        $drivers = ($this->getConfig()?->getAllDrivers() ?? []);
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
            Glitch::logException($e);
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
            $class = Archetype::resolve(FileStore::class, $namespace);
        } catch (ArchetypeException $e) {
            $class = GenericFileStore::class;
        }

        $config = $this->getConfig();
        $settings = $config?->getFileStoreSettings($namespace);

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
        // Base
        if (class_exists(Genesis::class)) {
            $basePath = Genesis::$hub->getLocalDataPath();
        } else {
            $basePath = getcwd();
        }

        $dir = Atlas::dir($basePath . '/stash/fileStore/');
        $dirs = [];

        if ($dir->exists()) {
            foreach ($dir->scanDirs() as $subDir) {
                $dirs[(string)$subDir] = true;
                yield (string)$subDir => $subDir;
            }
        }

        // Config
        $config = $this->getConfig();

        foreach ($config?->getAllFileStoreSettings() ?? [] as $name => $settings) {
            if (
                !isset($settings['path']) ||
                empty($settings['path'])
            ) {
                continue;
            }

            $dir = Atlas::dir(Coercion::toString($settings['path']));

            if (
                $dir->exists() &&
                !isset($dirs[(string)$dir])
            ) {
                yield (string)$dir => $dir;
            }
        }
    }
}


// Veneer
Veneer::register(Context::class, Stash::class);
