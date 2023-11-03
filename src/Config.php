<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

interface Config
{
    public function getDriverFor(
        string $namespace
    ): ?string;

    public function isDriverEnabled(
        string $driver
    ): bool;


    /**
     * @return array<string>
     */
    public function getAllDrivers(): array;

    /**
     * @return array<string, mixed>
     */
    public function getDriverSettings(
        string $driver
    ): ?array;

    /**
     * @return value-of<PileUpPolicy::KEYS>|null
     */
    public function getPileUpPolicy(
        string $namespace
    ): ?string;

    /**
     * @return positive-int|null
     */
    public function getPreemptTime(
        string $namespace
    ): ?int;

    /**
     * @return positive-int|null
     */
    public function getSleepTime(
        string $namespace
    ): ?int;

    /**
     * @return positive-int|null
     */
    public function getSleepAttempts(
        string $namespace
    ): ?int;
}
