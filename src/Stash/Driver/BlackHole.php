<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Stash;
use DecodeLabs\Stash\Driver;

class BlackHole implements Driver
{
    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * Init with settings
     */
    public function __construct(
        Stash $context,
        array $settings
    ) {
        unset($settings);
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
        return true;
    }

    /**
     * Fetch item data
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        return null;
    }

    /**
     * Remove item from store
     */
    public function delete(
        string $namespace,
        string $key
    ): bool {
        return true;
    }

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        return true;
    }



    /**
     * Save a lock for a key
     */
    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        return true;
    }

    /**
     * Get a lock expiry for a key
     */
    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        return null;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        return true;
    }


    /**
     * Count items
     */
    public function count(
        string $namespace
    ): int {
        return 0;
    }

    /**
     * Get keys
     */
    public function getKeys(
        string $namespace
    ): array {
        return [];
    }


    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        // whatever
    }
}
