<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use ArrayAccess;
use Closure;
use Psr\Cache\CacheItemPoolInterface as CacheItemPool;
use Psr\SimpleCache\CacheInterface as SimpleCache;

/**
 * @extends ArrayAccess<string, mixed>
 */
interface Store extends
    CacheItemPool,
    SimpleCache,
    ArrayAccess
{
    /**
     * @param non-empty-string $namespace
     */
    public function __construct(
        string $namespace,
        Driver $driver
    );

    public function getNamespace(): string;
    public function getDriver(): Driver;

    /**
     * @param Closure(Item, Store): mixed $generator
     */
    public function fetch(
        string $key,
        Closure $generator
    ): mixed;

    public function __set(
        string $key,
        mixed $value
    ): void;

    public function __get(string $key): Item;
    public function __isset(string $key): bool;
    public function __unset(string $key): void;

    public function clearDeferred(): bool;


    /**
     * @return $this
     */
    public function pileUpIgnore(): static;

    /**
     * @phpstan-param positive-int|null $time
     * @return $this
     */
    public function pileUpPreempt(int $time = null): static;

    /**
     * @phpstan-param positive-int|null $time
     * @phpstan-param positive-int|null $attempts
     * @return $this
     */
    public function pileUpSleep(
        int $time = null,
        int $attempts = null
    ): static;

    /**
     * @return $this
     */
    public function pileUpValue(): static;


    /**
     * @param value-of<PileUpPolicy::KEYS> $policy
     * @return $this
     */
    public function setPileUpPolicy(string $policy): static;

    /**
     * @return value-of<PileUpPolicy::KEYS>
     */
    public function getPileUpPolicy(): string;

    /**
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setPreemptTime(int $time): static;

    /**
     * @phpstan-return positive-int
     */
    public function getPreemptTime(): int;

    /**
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setSleepTime(int $time): static;

    /**
     * @phpstan-return positive-int
     */
    public function getSleepTime(): int;

    /**
     * @phpstan-param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(int $attempts): static;

    /**
     * @phpstan-return positive-int
     */
    public function getSleepAttempts(): int;
}
