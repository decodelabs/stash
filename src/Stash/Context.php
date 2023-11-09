<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use DecodeLabs\Archetype;
use DecodeLabs\Archetype\NotFoundException as ArchetypeException;
use DecodeLabs\Dovetail;
use DecodeLabs\Dovetail\Config\Stash as StashConfig;
use DecodeLabs\Exceptional;
use DecodeLabs\Glitch\Proxy as Glitch;
use DecodeLabs\Stash;
use DecodeLabs\Stash\Store\Generic as GenericStore;
use DecodeLabs\Veneer;
use ReflectionClass;
use Throwable;

class Context
{
    public const DRIVERS = [
        'Memcache', 'Redis', 'Apcu', 'Predis', 'PhpFile', 'PhpArray'
    ];

    /**
     * @var array<string, Store>
     */
    protected array $caches = [];

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
    public function purgeAll(): void
    {
        $drivers = ($this->getConfig()?->getAllDrivers() ?? []);
        $drivers[] = (new ReflectionClass($this->loadDriverFor('default')))->getShortName();
        $drivers = array_unique($drivers);

        foreach ($drivers as $name) {
            $this->purge($name);
        }
    }


    /**
     * Purge driver
     */
    public function purge(
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
}


// Veneer
Veneer::register(Context::class, Stash::class);
