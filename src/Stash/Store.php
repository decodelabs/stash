<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use ArrayAccess;
use Closure;
use Countable;
use DateInterval;
use Psr\Cache\CacheItemInterface as CacheItem;
use Psr\Cache\CacheItemPoolInterface as CacheItemPool;
use Psr\SimpleCache\CacheInterface as SimpleCache;

/**
 * @template T of mixed
 * @extends ArrayAccess<string, T>
 */
interface Store extends
    CacheItemPool,
    SimpleCache,
    ArrayAccess,
    Countable
{
    public function __construct(
        NamespaceConfig $namespace,
        Driver $driver
    );

    public function getNamespace(): string;
    public function getDriver(): Driver;

    /**
     * @param ?T $default
     * @return ?T
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed;

    /**
     * @return Item<T>
     */
    public function getItem(
        string $key
    ): Item;

    /**
     * @param iterable<int, string> $keys
     * @param ?T $default
     * @return iterable<string, ?T>
     */
    public function getMultiple(
        iterable $keys,
        mixed $default = null
    ): iterable;

    /**
     * @param array<string> $keys
     * @return iterable<string, Item<T>>
     */
    public function getItems(
        array $keys = []
    ): iterable;

    /**
     * @param Closure(Item<T>, Store<T>): T $generator
     * @return T
     */
    public function fetch(
        string $key,
        Closure $generator
    ): mixed;

    public function has(
        string $key,
        string ...$keys
    ): bool;

    public function delete(
        string $key,
        string ...$keys
    ): bool;

    public function deleteItem(
        string $key,
        string ...$keys
    ): bool;

    /**
     * @param T $value
     */
    public function set(
        string $key,
        mixed $value,
        int|DateInterval|null $ttl = null
    ): bool;

    /**
     * @param iterable<string, T> $values
     */
    public function setMultiple(
        iterable $values,
        int|DateInterval|null $ttl = null
    ): bool;

    /**
     * @param Item<T> $item
     */
    public function save(
        CacheItem $item
    ): bool;

    /**
     * @param Item<T> $item
     */
    public function saveDeferred(
        CacheItem $item
    ): bool;

    /**
     * @param T $value
     */
    public function __set(
        string $key,
        mixed $value
    ): void;

    /**
     * @return Item<T>
     */
    public function __get(
        string $key
    ): Item;

    public function __isset(
        string $key
    ): bool;

    public function __unset(
        string $key
    ): void;



    /**
     * @return array<string>
     */
    public function getDriverKeys(): array;


    public function clearDeferred(): bool;


    /**
     * @return $this
     */
    public function pileUpIgnore(): static;

    /**
     * @param positive-int|null $time
     * @return $this
     */
    public function pileUpPreempt(
        ?int $time = null
    ): static;

    /**
     * @param positive-int|null $time
     * @param positive-int|null $attempts
     * @return $this
     */
    public function pileUpSleep(
        ?int $time = null,
        ?int $attempts = null
    ): static;

    /**
     * @return $this
     */
    public function pileUpValue(): static;


    public function setPileUpPolicy(
        PileUpPolicy $policy
    ): static;

    public function getPileUpPolicy(): PileUpPolicy;

    /**
     * @param positive-int $time
     * @return $this
     */
    public function setPreemptTime(
        int $time
    ): static;

    /**
     * @return positive-int
     */
    public function getPreemptTime(): int;

    /**
     * @param positive-int $time
     * @return $this
     */
    public function setSleepTime(
        int $time
    ): static;

    /**
     * @return positive-int
     */
    public function getSleepTime(): int;

    /**
     * @param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(
        int $attempts
    ): static;

    /**
     * @return positive-int
     */
    public function getSleepAttempts(): int;
}
