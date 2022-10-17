<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use DecodeLabs\Archetype;
use DecodeLabs\Archetype\NotFoundException as ArchetypeException;
use DecodeLabs\Exceptional;
use DecodeLabs\Glitch\Proxy as Glitch;
use DecodeLabs\Stash\Store\Generic as GenericStore;
use Throwable;

class Context
{
    /**
     * @var array<string, Store>
     */
    protected array $caches = [];

    protected ?Config $config = null;


    /**
     * Set config
     */
    public function setConfig(?Config $config): void
    {
        $this->config = $config;
    }

    /**
     * Get config
     */
    public function getConfig(): ?Config
    {
        return $this->config;
    }



    /**
     * Get cache store by name
     */
    public function get(string $namespace): Store
    {
        if (isset($this->caches[$namespace])) {
            return $this->caches[$namespace];
        }

        $driver = $this->getDriverFor($namespace);

        try {
            $class = Archetype::resolve(Store::class, $namespace);
        } catch (ArchetypeException $e) {
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

        return $store;
    }

    /**
     * Get driver for namespace
     */
    public function getDriverFor(string $namespace): Driver
    {
        $options = [/*'Memcache', 'Redis', 'Apcu', 'Predis', 'PhpFile',*/ 'PhpArray'];

        if (null !== ($driverName = $this->config?->getDriverFor($namespace))) {
            array_unshift($options, $driverName);
        }

        foreach ($options as $name) {
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
    public function loadDriver(string $name): ?Driver
    {
        $class = Archetype::resolve(Driver::class, $name);

        if (
            !$class::isAvailable() ||
            !($this->config?->isDriverEnabled($name) ?? true)
        ) {
            return null;
        }

        $settings = $this->config?->getDriverSettings($name) ?? [];
        return new $class($settings);
    }
}
