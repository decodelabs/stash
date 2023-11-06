<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Store;

use Closure;
use DateInterval;

use DecodeLabs\Coercion;
use DecodeLabs\Exceptional;
use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\Item;
use DecodeLabs\Stash\PileUpPolicy;
use DecodeLabs\Stash\Store;

use Psr\Cache\CacheItemInterface as CacheItem;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;

class Generic implements Store
{
    protected string $namespace;

    /**
     * @var array<string, Item>
     */
    protected array $deferred = [];

    protected PileUpPolicy $pileUpPolicy = PileUpPolicy::PREEMPT;

    /**
     * @phpstan-var positive-int
     */
    protected int $preemptTime = 30;

    /**
     * @phpstan-var positive-int
     */
    protected int $sleepTime = 500;

    /**
     * @phpstan-var positive-int
     */
    protected int $sleepAttempts = 10;

    protected Driver $driver;



    /**
     * Init with namespace and driver
     */
    public function __construct(
        string $namespace,
        Driver $driver
    ) {
        $this->driver = $driver;
        $this->namespace = $namespace;
    }

    /**
     * Get active driver
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * Get active namespace
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }



    /**
     * Fetches a value from the cache.
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $item = $this->wrapSimpleErrors(function () use ($key) {
            return $this->getItem($key);
        });

        if ($item->isHit()) {
            return $item->get();
        } else {
            return $default;
        }
    }


    /**
     * Retrive item object, regardless of hit or miss
     */
    public function getItem(
        string $key
    ): Item {
        $key = $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            return clone $this->deferred[$key];
        }

        return new Item($this, $key);
    }


    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<int, string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(
        iterable $keys,
        mixed $default = null
    ): iterable {
        $items = $this->wrapSimpleErrors(function () use ($keys) {
            return $this->getItems(Coercion::iterableToArray($keys));
        });

        foreach ($items as $key => $item) {
            if ($item->isHit()) {
                yield $key => $item->get();
            } else {
                yield $key => $default;
            }
        }
    }


    /**
     * Retrieve a list of items
     *
     * @param array<string> $keys
     * @return iterable<string, Item>
     */
    public function getItems(
        array $keys = []
    ): iterable {
        $output = [];

        foreach ($keys as $key) {
            $item = $this->getItem($key);
            $output[$item->getKey()] = $item;
        }

        return $output;
    }


    /**
     * Determines whether an item is present in the cache.
     */
    public function has(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        return $this->wrapSimpleErrors(function () use ($keys) {
            foreach ($keys as $key) {
                if ($this->hasItem($key)) {
                    return true;
                }
            }

            return false;
        });
    }


    /**
     * Confirms if the cache contains specified cache item.
     */
    public function hasItem(
        string $key
    ): bool {
        return $this->getItem($key)->isHit();
    }


    /**
     * Deletes all items in the pool.
     */
    public function clear(): bool
    {
        $this->clearDeferred();
        return $this->driver->clearAll($this->namespace);
    }


    /**
     * Forget things that have been deferred
     */
    public function clearDeferred(): bool
    {
        $this->deferred = [];
        return true;
    }


    /**
     * Delete an item from the cache by its unique key.
     */
    public function delete(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        return $this->wrapSimpleErrors(function () use ($keys) {
            return $this->deleteItems($keys);
        });
    }


    /**
     * Removes the item from the pool.
     */
    public function deleteItem(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        return $this->deleteItems($keys);
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<int, string> $keys
     */
    public function deleteMultiple(
        iterable $keys
    ): bool {
        /** @var array<string> */
        $keys = Coercion::iterableToArray($keys);

        return $this->wrapSimpleErrors(function () use ($keys) {
            return $this->deleteItems($keys);
        });
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param array<string> $keys
     */
    public function deleteItems(
        array $keys
    ): bool {
        $output = true;

        foreach ($keys as $key) {
            $key = $this->validateKey($key);
            unset($this->deferred[$key]);

            if (!$this->driver->delete($this->namespace, $key)) {
                $output = false;
            }
        }

        return $output;
    }


    /**
     * Get item, if miss, set $key as result of $generator
     */
    public function fetch(
        string $key,
        Closure $generator
    ): mixed {
        $item = $this->getItem($key);

        if (
            $item instanceof Item &&
            $item->isMiss()
        ) {
            $item->lock();
            $value = $generator($item, $this);
            $item->set($value);
            $item->save();
        }

        return $item->get();
    }


    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     */
    public function set(
        string $key,
        mixed $value,
        int|DateInterval|null $ttl = null
    ): bool {
        $item = $this->wrapSimpleErrors(function () use ($key, $ttl) {
            $item = $this->getItem($key);
            return $item->expiresAfter($ttl);
        });

        $item->set($value);
        return $this->save($item);
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(
        iterable $values,
        int|DateInterval|null $ttl = null
    ): bool {
        $values = Coercion::iterableToArray($values);

        return $this->wrapSimpleErrors(function () use ($values, $ttl) {
            $items = $this->getItems(array_keys($values));
            $success = true;

            foreach ($items as $key => $item) {
                $item->set($values[$key]);
                $item->expiresAfter($ttl);
                $success = $success && $this->saveDeferred($item);
            }

            return $success && $this->commit();
        });
    }



    /**
     * Persists a cache item immediately.
     */
    public function save(
        CacheItem $item
    ): bool {
        $item = $this->checkCacheItem($item);
        return $item->save();
    }


    /**
     * Sets a cache item to be persisted later.
     */
    public function saveDeferred(
        CacheItem $item
    ): bool {
        $item = $this->checkCacheItem($item);
        $this->deferred[$item->getKey()] = $item;
        return true;
    }


    /**
     * Persists any deferred cache items.
     */
    public function commit(): bool
    {
        $output = true;

        foreach ($this->deferred as $key => $item) {
            if (!$item->save()) {
                $output = false;
            }

            unset($this->deferred[$key]);
        }

        $this->deferred = [];
        return $output;
    }



    /**
     * Shortcut set
     */
    public function __set(
        string $key,
        mixed $value
    ): void {
        $this->set($key, $value);
    }

    /**
     * Shortcut getItem()
     */
    public function __get(
        string $key
    ): Item {
        return $this->getItem($key);
    }

    /**
     * Shortcut hasItem()
     */
    public function __isset(
        string $key
    ): bool {
        return $this->hasItem($key);
    }

    /**
     * Shortcut delete item
     */
    public function __unset(
        string $key
    ): void {
        $this->deleteItem($key);
    }



    /**
     * Shortcut set()
     */
    public function offsetSet(
        mixed $key,
        mixed $value
    ): void {
        $this->set((string)$key, $value);
    }

    /**
     * Shortcut get()
     */
    public function offsetGet(
        mixed $key
    ): mixed {
        return $this->get($key);
    }

    /**
     * Shortcut has()
     */
    public function offsetExists(
        mixed $key
    ): bool {
        return $this->has($key);
    }

    /**
     * Shortcut delete()
     *
     * @param string $key
     */
    public function offsetUnset(
        mixed $key
    ): void {
        $this->delete($key);
    }





    /**
     * Set pile up policy to ignore
     */
    public function pileUpIgnore(): static
    {
        $this->pileUpPolicy = PileUpPolicy::IGNORE;
        return $this;
    }

    /**
     * Set pile up policy to preempt
     */
    public function pileUpPreempt(
        int $preemptTime = null
    ): static {
        $this->pileUpPolicy = PileUpPolicy::PREEMPT;

        if ($preemptTime !== null) {
            $this->preemptTime = $preemptTime;
        }

        return $this;
    }

    /**
     * Set pile up policy to sleep
     */
    public function pileUpSleep(
        int $time = null,
        int $attempts = null
    ): static {
        $this->pileUpPolicy = PileUpPolicy::SLEEP;

        if ($time !== null) {
            $this->sleepTime = $time;
        }

        if ($attempts !== null) {
            $this->sleepAttempts = $attempts;
        }

        return $this;
    }

    /**
     * Set pile up policy to return value
     */
    public function pileUpValue(): static
    {
        $this->pileUpPolicy = PileUpPolicy::VALUE;
        return $this;
    }


    /**
     * Set pile up policy
     */
    public function setPileUpPolicy(
        PileUpPolicy $policy
    ): static {
        $this->pileUpPolicy = $policy;
        return $this;
    }

    /**
     * Get pile up policy
     */
    public function getPileUpPolicy(): PileUpPolicy
    {
        return $this->pileUpPolicy;
    }


    /**
     * Replace preempt time
     */
    public function setPreemptTime(
        int $preemptTime
    ): static {
        $this->preemptTime = $preemptTime;
        return $this;
    }

    /**
     * Get preempt time
     */
    public function getPreemptTime(): int
    {
        return $this->preemptTime;
    }


    /**
     * Replace sleep time
     */
    public function setSleepTime(
        int $time
    ): static {
        $this->sleepTime = $time;
        return $this;
    }

    /**
     * Get sleep time
     */
    public function getSleepTime(): int
    {
        return $this->sleepTime;
    }

    /**
     * Replace sleep attempts
     */
    public function setSleepAttempts(
        int $attempts
    ): static {
        $this->sleepAttempts = $attempts;
        return $this;
    }

    /**
     * Get sleep attempts
     */
    public function getSleepAttempts(): int
    {
        return $this->sleepAttempts;
    }





    /**
     * Validate single key
     */
    protected function validateKey(
        string $key
    ): string {
        if (!strlen($key)) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                'Cache key must be a non-empty string',
                null,
                $key
            );
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                'Cache key must not contain reserved extension characters: {}()/\@:',
                null,
                $key
            );
        }

        return $key;
    }


    /**
     * Check cache item
     */
    protected function checkCacheItem(
        CacheItem $item
    ): Item {
        if (!$item instanceof Item) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                'Cache items must implement ' . Item::class,
                null,
                $item
            );
        }

        return $item;
    }


    /**
     * Wrap simple errors
     *
     * @template T
     * @param Closure(): T $func
     * @return T
     */
    protected function wrapSimpleErrors(
        Closure $func
    ): mixed {
        try {
            return $func();
        } catch (CacheInvalidArgumentException $e) {
            throw Exceptional::{'InvalidArgument,Psr\\SimpleCache\\InvalidArgumentException'}(
                $e->getMessage(),
                ['previous' => $e]
            );
        }
    }
}
