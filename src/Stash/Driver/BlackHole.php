<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Stash\Driver;

class BlackHole implements Driver
{
    public static function isAvailable(): bool
    {
        return true;
    }

    public function __construct()
    {
    }

    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool {
        return true;
    }

    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        return null;
    }

    public function delete(
        string $namespace,
        string $key
    ): bool {
        return true;
    }

    public function clearAll(
        string $namespace
    ): bool {
        return true;
    }


    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        return true;
    }

    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        return null;
    }

    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        return true;
    }


    public function count(
        string $namespace
    ): int {
        return 0;
    }

    public function getKeys(
        string $namespace
    ): array {
        return [];
    }

    public function purge(): void
    {
        // whatever
    }
}
