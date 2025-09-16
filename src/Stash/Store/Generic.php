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
use DecodeLabs\Stash\NamespaceConfig;
use DecodeLabs\Stash\PileUpPolicy;
use DecodeLabs\Stash\Store;
use Psr\Cache\CacheItemInterface as CacheItem;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Throwable;

/**
 * @template T of mixed
 * @implements Store<T>
 */
class Generic implements Store
{
    protected string $namespace;
    /**
     * @var array<string,Item<T>>
     */
    protected array $deferred = [];

    protected PileUpPolicy $pileUpPolicy = PileUpPolicy::Preempt;

    /**
     * @var positive-int
     */
    protected int $preemptTime = 30;

    /**
     * @var positive-int
     */
    protected int $sleepTime = 500;

    /**
     * @var positive-int
     */
    protected int $sleepAttempts = 10;


    public function __construct(
        NamespaceConfig $config,
        protected Driver $driver
    ) {
        $this->namespace = $config->namespace;

        if ($config->pileUpPolicy !== null) {
            $this->pileUpPolicy = $config->pileUpPolicy;
        }

        if ($config->preemptTime !== null) {
            $this->preemptTime = $config->preemptTime;
        }

        if ($config->sleepTime !== null) {
            $this->sleepTime = $config->sleepTime;
        }

        if ($config->sleepAttempts !== null) {
            $this->sleepAttempts = $config->sleepAttempts;
        }
    }


    public function getDriver(): Driver
    {
        return $this->driver;
    }


    public function getNamespace(): string
    {
        return $this->namespace;
    }


    /**
     * @param ?T $default
     * @return ?T
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
     * @return Item<T>
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
     * @param iterable<int, string> $keys
     * @param ?T $default
     * @return iterable<string, ?T>
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
     * @param array<string> $keys
     * @return iterable<string, Item<T>>
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


    public function hasItem(
        string $key
    ): bool {
        return $this->getItem($key)->isHit();
    }


    public function clear(): bool
    {
        $this->clearDeferred();
        return $this->driver->clearAll($this->namespace);
    }


    public function clearDeferred(): bool
    {
        $this->deferred = [];
        return true;
    }


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


    public function deleteItem(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        return $this->deleteItems($keys);
    }

    /**
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


    public function fetch(
        string $key,
        Closure $generator
    ): mixed {
        $item = $this->getItem($key);

        if ($item->isMiss()) {
            $item->lock();

            try {
                $value = $generator($item, $this);
            } catch (Throwable $e) {
                $item->unlock();
                throw $e;
            }

            $item->set($value);
            $item->save();
        }

        /** @var T $output */
        $output = $item->get();
        return $output;
    }


    /**
     * @param T $value
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
     * @param iterable<string, T> $values
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

                $success =
                    $success &&
                    $this->saveDeferred($item);
            }

            return
                $success &&
                $this->commit();
        });
    }



    /**
     * @param Item<T> $item
     */
    public function save(
        CacheItem $item
    ): bool {
        $item = $this->checkCacheItem($item);
        return $item->save();
    }


    /**
     * @param Item<T> $item
     */
    public function saveDeferred(
        CacheItem $item
    ): bool {
        $item = $this->checkCacheItem($item);
        $this->deferred[$item->getKey()] = $item;
        return true;
    }


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



    public function __set(
        string $key,
        mixed $value
    ): void {
        $this->set($key, $value);
    }

    public function __get(
        string $key
    ): Item {
        return $this->getItem($key);
    }

    public function __isset(
        string $key
    ): bool {
        return $this->hasItem($key);
    }

    public function __unset(
        string $key
    ): void {
        $this->deleteItem($key);
    }



    /**
     * @param T $value
     */
    public function offsetSet(
        mixed $key,
        mixed $value
    ): void {
        $this->set((string)$key, $value);
    }

    /**
     * @return ?T
     */
    public function offsetGet(
        mixed $key
    ): mixed {
        return $this->get($key);
    }

    public function offsetExists(
        mixed $key
    ): bool {
        return $this->has($key);
    }

    /**
     * @param string $key
     */
    public function offsetUnset(
        mixed $key
    ): void {
        $this->delete($key);
    }




    public function count(): int
    {
        return $this->driver->count($this->namespace);
    }


    public function getDriverKeys(): array
    {
        return $this->driver->getKeys($this->namespace);
    }




    public function pileUpIgnore(): static
    {
        $this->pileUpPolicy = PileUpPolicy::Ignore;
        return $this;
    }


    public function pileUpPreempt(
        ?int $preemptTime = null
    ): static {
        $this->pileUpPolicy = PileUpPolicy::Preempt;

        if ($preemptTime !== null) {
            $this->preemptTime = $preemptTime;
        }

        return $this;
    }


    public function pileUpSleep(
        ?int $time = null,
        ?int $attempts = null
    ): static {
        $this->pileUpPolicy = PileUpPolicy::Sleep;

        if ($time !== null) {
            $this->sleepTime = $time;
        }

        if ($attempts !== null) {
            $this->sleepAttempts = $attempts;
        }

        return $this;
    }


    public function pileUpValue(): static
    {
        $this->pileUpPolicy = PileUpPolicy::Value;
        return $this;
    }



    public function setPileUpPolicy(
        PileUpPolicy $policy
    ): static {
        $this->pileUpPolicy = $policy;
        return $this;
    }


    public function getPileUpPolicy(): PileUpPolicy
    {
        return $this->pileUpPolicy;
    }



    public function setPreemptTime(
        int $preemptTime
    ): static {
        $this->preemptTime = $preemptTime;
        return $this;
    }


    public function getPreemptTime(): int
    {
        return $this->preemptTime;
    }



    public function setSleepTime(
        int $time
    ): static {
        $this->sleepTime = $time;
        return $this;
    }


    public function getSleepTime(): int
    {
        return $this->sleepTime;
    }


    public function setSleepAttempts(
        int $attempts
    ): static {
        $this->sleepAttempts = $attempts;
        return $this;
    }


    public function getSleepAttempts(): int
    {
        return $this->sleepAttempts;
    }






    protected function validateKey(
        string $key
    ): string {
        if (!strlen($key)) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                message: 'Cache key must be a non-empty string',
                data: $key
            );
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                message: 'Cache key must not contain reserved extension characters: {}()/\@:',
                data: $key
            );
        }

        return $key;
    }


    /**
     * @return Item<T>
     */
    protected function checkCacheItem(
        CacheItem $item
    ): Item {
        if (!$item instanceof Item) {
            throw Exceptional::{'InvalidArgument,Psr\\Cache\\InvalidArgumentException'}(
                message: 'Cache items must implement ' . Item::class,
                data: $item
            );
        }

        return $item;
    }


    /**
     * @template E
     * @param Closure(): E $func
     * @return E
     */
    protected function wrapSimpleErrors(
        Closure $func
    ): mixed {
        try {
            return $func();
        } catch (CacheInvalidArgumentException $e) {
            throw Exceptional::{'InvalidArgument,Psr\\SimpleCache\\InvalidArgumentException'}(
                message: $e->getMessage(),
                previous: $e
            );
        }
    }
}
