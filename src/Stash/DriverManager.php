<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use DecodeLabs\Exceptional;
use DecodeLabs\Stash\DriverConfig\Fallback as FallbackDriverConfig;
use DecodeLabs\Stash\DriverConfig\Memcache as MemcacheDriverConfig;
use DecodeLabs\Stash\DriverConfig\Redis as RedisDriverConfig;
use Throwable;

class DriverManager
{
    /**
     * @var array<string,DriverConfig>
     */
    public protected(set) array $driverConfigs = [];

    /**
     * @var array<string,NamespaceConfig>
     */
    public protected(set) array $namespaces = [];

    /**
     * @var array<string,Driver>
     */
    public protected(set) array $drivers = [];

    /**
     * @var array<string,FileStoreConfig>
     */
    public protected(set) array $fileStoreConfigs = [];

    public function __construct(
        DriverConfig|NamespaceConfig|FileStoreConfig ...$settings
    ) {
        foreach ($settings as $setting) {
            if ($setting instanceof DriverConfig) {
                $this->driverConfigs[$setting->name] = $setting;
            } elseif ($setting instanceof FileStoreConfig) {
                $this->fileStoreConfigs[$setting->namespace] = $setting;
            } elseif ($setting instanceof NamespaceConfig) {
                $this->namespaces[$setting->namespace] = $setting;
            }
        }
    }

    public function getNamespaceConfig(
        string $namespace
    ): NamespaceConfig {
        if (isset($this->namespaces[$namespace])) {
            return $this->namespaces[$namespace];
        }

        if (isset($this->namespaces['default'])) {
            return $this->namespaces['default'];
        }

        return new NamespaceConfig($namespace);
    }

    public function getDriverConfig(
        NamespaceConfig $namespace,
        ?string $defaultPrefix = null
    ): DriverConfig {
        $this->ensureDefaultDrivers($defaultPrefix);

        if (isset($namespace->driver)) {
            if (is_a($namespace->driver, DriverConfig::class, true)) {
                foreach ($this->driverConfigs as $driver) {
                    if ($driver instanceof $namespace->driver) {
                        return $driver;
                    }
                }
            }

            if (isset($this->driverConfigs[$namespace->driver])) {
                return $this->driverConfigs[$namespace->driver];
            }
        }

        if (isset($this->driverConfigs['default'])) {
            return $this->driverConfigs['default'];
        }

        return $this->driverConfigs[array_key_first($this->driverConfigs)];
    }

    public function ensureDefaultDrivers(
        ?string $defaultPrefix = null
    ): void {
        if (empty($this->driverConfigs)) {
            $this->tryLocalDrivers();
        }

        if (!isset($this->driverConfigs['Fallback'])) {
            $this->driverConfigs['Fallback'] = new FallbackDriverConfig('Fallback', $defaultPrefix);
        }
    }

    private function tryLocalDrivers(): void
    {
        // Memcache
        if (extension_loaded('memcached')) {
            try {
                $fp = fsockopen('localhost', 11211, $errno, $errstr, 0.01);
            } catch (Throwable $e) {
                $fp = null;
            }

            if ($fp) {
                fclose($fp);
                $this->driverConfigs['Memcache'] = new MemcacheDriverConfig('Memcache');
            }
        }

        // Redis
        if (extension_loaded('redis')) {
            try {
                $fp = fsockopen('localhost', 6379, $errno, $errstr, 0.01);
            } catch (Throwable $e) {
                $fp = null;
            }

            if ($fp) {
                fclose($fp);
                $this->driverConfigs['Redis'] = new RedisDriverConfig('Redis');
            }
        }
    }

    public function registerDriver(
        string $name,
        Driver $driver
    ): void {
        $this->drivers[$name] = $driver;
    }

    public function getDriver(
        DriverConfig $config
    ): Driver {
        if (isset($this->drivers[$config->name])) {
            return $this->drivers[$config->name];
        }

        $configs = [$config, ...$this->driverConfigs];
        $configs = array_unique($configs, SORT_REGULAR);

        foreach ($configs as $config) {
            foreach ($config->driverClasses as $driverClass) {
                if (!$driverClass::isAvailable()) {
                    continue;
                }

                return $this->drivers[$config->name] = new $driverClass($config);
            }
        }

        throw Exceptional::ComponentUnavailable(
            message: 'No cache drivers available for namespace: ' . $config->name
        );
    }

    public function getDriverForNamespace(
        NamespaceConfig $namespace,
        ?string $defaultPrefix = null
    ): Driver {
        $config = $this->getDriverConfig($namespace, $defaultPrefix);
        return $this->getDriver($config);
    }

    public function getFileStoreConfig(
        string $namespace
    ): FileStoreConfig {
        if (isset($this->fileStoreConfigs[$namespace])) {
            return $this->fileStoreConfigs[$namespace];
        }

        if (isset($this->fileStoreConfigs['default'])) {
            return $this->fileStoreConfigs['default'];
        }

        return new FileStoreConfig($namespace);
    }
}
