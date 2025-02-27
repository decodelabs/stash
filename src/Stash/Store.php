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
     * Fetches a value from the cache.
     *
     * @param ?T $default
     * @return ?T
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed;

    /**
     * Retrive item object, regardless of hit or miss
     *
     * @return Item<T>
     */
    public function getItem(
        string $key
    ): Item;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<int, string> $keys
     * @param ?T $default
     * @return iterable<string, ?T>
     */
    public function getMultiple(
        iterable $keys,
        mixed $default = null
    ): iterable;

    /**
     * Retrieve a list of items
     *
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

    /**
     * Determines whether an item is present in the cache.
     */
    public function has(
        string $key,
        string ...$keys
    ): bool;

    /**
     * Delete an item from the cache by its unique key.
     */
    public function delete(
        string $key,
        string ...$keys
    ): bool;

    /**
     * Removes the item from the pool.
     */
    public function deleteItem(
        string $key,
        string ...$keys
    ): bool;

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param T $value
     */
    public function set(
        string $key,
        mixed $value,
        int|DateInterval|null $ttl = null
    ): bool;

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable<string, T> $values
     */
    public function setMultiple(
        iterable $values,
        int|DateInterval|null $ttl = null
    ): bool;

    /**
     * Persists a cache item immediately.
     *
     * @param Item<T> $item
     */
    public function save(
        CacheItem $item
    ): bool;

    /**
     * Sets a cache item to be persisted later.
     *
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
     * @phpstan-param positive-int|null $time
     * @return $this
     */
    public function pileUpPreempt(
        ?int $time = null
    ): static;

    /**
     * @phpstan-param positive-int|null $time
     * @phpstan-param positive-int|null $attempts
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
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setPreemptTime(
        int $time
    ): static;

    /**
     * @phpstan-return positive-int
     */
    public function getPreemptTime(): int;

    /**
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setSleepTime(
        int $time
    ): static;

    /**
     * @phpstan-return positive-int
     */
    public function getSleepTime(): int;

    /**
     * @phpstan-param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(
        int $attempts
    ): static;

    /**
     * @phpstan-return positive-int
     */
    public function getSleepAttempts(): int;
}
