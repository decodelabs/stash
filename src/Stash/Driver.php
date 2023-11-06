<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

interface Driver
{
    public static function isAvailable(): bool;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        array $settings
    );

    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool;

    /**
     * @return array{0: mixed, 1: ?int}|null
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array;

    public function delete(
        string $namespace,
        string $key
    ): bool;

    public function clearAll(
        string $namespace
    ): bool;


    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool;

    public function fetchLock(
        string $namespace,
        string $key
    ): ?int;

    public function deleteLock(
        string $namespace,
        string $key
    ): bool;


    public function count(
        string $namespace
    ): int;

    /**
     * @return array<string>
     */
    public function getKeys(
        string $namespace
    ): array;

    public function purge(): void;
}
