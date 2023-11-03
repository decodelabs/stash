<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Archetype;
use DecodeLabs\Stash\Driver;

class Composite implements Driver
{
    /**
     * @var array<Driver>
     */
    protected array $drivers = [];

    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * Init with drivers
     */
    public function __construct(
        array $settings
    ) {
        foreach ($settings as $name => $driverSettings) {
            $class = Archetype::resolve(Driver::class, $name);

            if (!$class::isAvailable()) {
                continue;
            }

            $this->drivers[] = new $class($driverSettings);
        }
    }

    /**
     * Store item data
     */
    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool {
        $output = true;

        foreach (array_reverse($this->drivers) as $driver) {
            $output = $output && $driver->store($namespace, $key, $value, $created, $expires);
        }

        return $output;
    }

    /**
     * Fetch item data
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        foreach ($this->drivers as $driver) {
            $data = $driver->fetch($namespace, $key);

            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Remove item from store
     */
    public function delete(
        string $namespace,
        string $key
    ): bool {
        $output = true;

        foreach (array_reverse($this->drivers) as $driver) {
            $output = $output && $driver->delete($namespace, $key);
        }

        return $output;
    }

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        $output = true;

        foreach (array_reverse($this->drivers) as $driver) {
            $output = $output && $driver->clearAll($namespace);
        }

        return $output;
    }



    /**
     * Save a lock for a key
     */
    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $output = true;

        foreach (array_reverse($this->drivers) as $driver) {
            $output = $output && $driver->storeLock($namespace, $key, $expires);
        }

        return $output;
    }

    /**
     * Get a lock expiry for a key
     */
    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        foreach ($this->drivers as $driver) {
            $data = $driver->fetchLock($namespace, $key);

            if (is_int($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $output = true;

        foreach (array_reverse($this->drivers) as $driver) {
            $output = $output && $driver->deleteLock($namespace, $key);
        }

        return $output;
    }


    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        foreach (array_reverse($this->drivers) as $driver) {
            $driver->purge();
        }
    }
}
