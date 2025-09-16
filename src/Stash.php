<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use DateInterval;
use DecodeLabs\Atlas\Dir;
use DecodeLabs\Kingdom\Service;
use DecodeLabs\Kingdom\ServiceTrait;
use DecodeLabs\Stash\Config;
use DecodeLabs\Stash\DriverManager;
use DecodeLabs\Stash\FileStore;
use DecodeLabs\Stash\FileStore\Generic as GenericFileStore;
use DecodeLabs\Stash\Store;
use DecodeLabs\Stash\Store\Generic as GenericStore;
use Generator;
use Stringable;

class Stash implements Service
{
    use ServiceTrait;

    public ?string $defaultPrefix = null;

    /**
     * @var array<string,Store<mixed>>
     */
    protected array $caches = [];

    /**
     * @var array<string, FileStore>
     */
    protected array $fileStores = [];

    public function __construct(
        protected(set) Archetype $archetype,
        protected(set) DriverManager $driverManager,
    ) {
    }

    /**
     * @return Store<mixed>
     */
    public function load(
        string $namespace
    ): Store {
        if (isset($this->caches[$namespace])) {
            return $this->caches[$namespace];
        }

        $storeClass = $this->archetype->tryResolve(Store::class, $namespace) ?? GenericStore::class;
        $config = $this->driverManager->getNamespaceConfig($namespace);
        $driver = $this->driverManager->getDriverForNamespace($config, $this->defaultPrefix);

        return $this->caches[$config->namespace] = new $storeClass($config, $driver);
    }


    public function purge(): void
    {
        $this->driverManager->ensureDefaultDrivers($this->defaultPrefix);

        foreach ($this->driverManager->driverConfigs as $config) {
            $driver = $this->driverManager->getDriver($config);
            $driver->purge();
        }
    }



    public function loadFileStore(
        string $namespace
    ): FileStore {
        if (isset($this->fileStores[$namespace])) {
            return $this->fileStores[$namespace];
        }

        $class = $this->archetype->tryResolve(FileStore::class, $namespace) ?? GenericFileStore::class;
        $config = $this->driverManager->getFileStoreConfig($namespace);
        $config->prefix ??= $this->defaultPrefix;

        return $this->fileStores[$namespace] = new $class($config);
    }

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

    public function purgeFileStores(): void
    {
        foreach ($this->scanFileStoreDirectories() as $dir) {
            $dir->delete();
        }
    }

    /**
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
        foreach ($this->driverManager->fileStoreConfigs as $config) {
            if ($config->path !== null) {
                $dir = Atlas::getDir($dir);
                yield (string)$dir => $dir;
            }
        }
    }
}
