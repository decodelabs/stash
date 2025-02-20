<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use DecodeLabs\Coercion;
use Psr\Cache\CacheItemInterface as CacheItem;
use Stringable;

/**
 * @template T of mixed
 */
class Item implements CacheItem
{
    public const int LockTTL = 30;

    protected(set) string $key;

    /**
     * @var ?T
     */
    protected(set) mixed $value;

    protected(set) bool $hit = false;
    protected bool $fetched = false;

    protected(set) ?Carbon $expiration = null;
    protected(set) bool $locked = false;

    protected(set) ?PileUpPolicy $pileUpPolicy = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected(set) ?int $preemptTime = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected(set) ?int $sleepTime = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected(set) ?int $sleepAttempts = null;

    protected(set) mixed $fallbackValue = null;

    /**
     * @var Store<T>
     */
    protected(set) Store $store;

    /**
     * Init with store and key
     *
     * @param Store<T> $store
     */
    public function __construct(
        Store $store,
        string $key
    ) {
        $this->key = $key;
        $this->store = $store;
    }


    /**
     * Returns the key for the current cache item.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Sets the value represented by this cache item.
     *
     * @param T $value
     * @return $this
     */
    public function set(
        mixed $value
    ): static {
        $this->value = $value;
        $this->hit = true;
        $this->fetched = true;
        return $this;
    }

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
     *
     * @return ?T
     */
    public function get(): mixed
    {
        if (!$this->isHit()) {
            return null;
        }

        return $this->value;
    }

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     */
    public function isHit(): bool
    {
        $this->ensureFetched();

        if (!$this->hit) {
            return false;
        }

        if ($this->expiration !== null) {
            return $this->expiration->getTimestamp() > time();
        }

        return true;
    }

    /**
     * Invert of isHit()
     */
    public function isMiss(): bool
    {
        return !$this->isHit();
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @return $this
     */
    public function expiresAt(
        DateTimeInterface|DateInterval|string|int|null $expiration
    ): static {
        if ($expiration === null) {
            $this->expiration = null;
            return $this;
        }

        $this->expiration = Carbon::instance(Coercion::asDateTime($expiration));
        return $this;
    }

    /**
     * Sets the relative expiration time for this cache item.
     *
     * @return $this
     */
    public function expiresAfter(
        DateInterval|string|int|null $time
    ): static {
        if ($time === null) {
            $this->expiration = null;
            return $this;
        }

        $date = new Carbon();
        $date->add(Coercion::asDateInterval($time));

        $this->expiration = $date;
        return $this;
    }


    /**
     * Work out best expiration from value
     *
     * @return $this
     */
    public function setExpiration(
        DateTimeInterface|DateInterval|string|int|null $expiration
    ): static {
        if (
            $expiration instanceof DateInterval ||
            is_string($expiration) ||
            (
                is_int($expiration) &&
                $expiration < time() / 10
            )
        ) {
            $this->expiresAfter($expiration);
            return $this;
        } else {
            $this->expiresAt($expiration);
            return $this;
        }
    }


    /**
     * Get actual expiration date (if not permanent)
     */
    public function getExpiration(): ?Carbon
    {
        return $this->expiration;
    }

    /**
     * Get expiration as timestamp int
     */
    public function getExpirationTimestamp(): ?int
    {
        if (!$this->expiration) {
            return null;
        }

        return $this->expiration->getTimestamp();
    }


    /**
     * Get time until expiration
     */
    public function getTimeRemaining(): ?CarbonInterval
    {
        if (!$this->expiration) {
            return null;
        }

        $output = Carbon::now()->diff($this->expiration);

        if ($output->invert) {
            return new CarbonInterval(0);
        } else {
            return CarbonInterval::instance($output);
        }
    }


    /**
     * Set pile up policy to ignore
     *
     * @return $this
     */
    public function pileUpIgnore(): static
    {
        $this->pileUpPolicy = PileUpPolicy::Ignore;
        return $this;
    }

    /**
     * Set pile up policy to preempt
     *
     * @phpstan-param positive-int|null $time
     * @return $this
     */
    public function pileUpPreempt(
        ?int $time = null
    ): static {
        $this->pileUpPolicy = PileUpPolicy::Preempt;

        if ($time !== null) {
            $this->preemptTime = $time;
        }

        return $this;
    }

    /**
     * Set pile up policy to sleep
     *
     * @phpstan-param positive-int|null $time
     * @phpstan-param positive-int|null $attempts
     * @return $this
     */
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

    /**
     * Set pile up policy to return value
     *
     * @return $this
     */
    public function pileUpValue(
        mixed $value
    ): static {
        $this->pileUpPolicy = PileUpPolicy::Value;
        $this->fallbackValue = $value;
        return $this;
    }


    /**
     * Set pile up policy
     *
     * @return $this
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
        return $this->pileUpPolicy ?? $this->store->getPileUpPolicy();
    }


    /**
     * Replace preempt time
     *
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setPreemptTime(
        int $time
    ): static {
        $this->preemptTime = $time;
        return $this;
    }

    /**
     * Get preempt time
     *
     * @phpstan-return positive-int
     */
    public function getPreemptTime(): int
    {
        return $this->preemptTime ?? $this->store->getPreemptTime();
    }


    /**
     * Replace sleep time
     *
     * @phpstan-param positive-int $time
     * @return $this
     */
    public function setSleepTime(
        int $time
    ): static {
        $this->sleepTime = $time;
        return $this;
    }

    /**
     * Get sleep time
     *
     * @phpstan-return positive-int
     */
    public function getSleepTime(): int
    {
        return $this->sleepTime ?? $this->store->getSleepTime();
    }

    /**
     * Replace sleep attempts
     *
     * @phpstan-param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(
        int $attempts
    ): static {
        $this->sleepAttempts = $attempts;
        return $this;
    }

    /**
     * Get sleep attempts
     *
     * @phpstan-return positive-int
     */
    public function getSleepAttempts(): int
    {
        return $this->sleepAttempts ?? $this->store->getSleepAttempts();
    }


    /**
     * Replace fallback value
     *
     * @return $this
     */
    public function setFallbackValue(
        mixed $value
    ): static {
        $this->fallbackValue = $value;
        return $this;
    }

    /**
     * Get fallback value
     */
    public function getFallbackValue(): mixed
    {
        return $this->fallbackValue;
    }


    /**
     * Add lock entry to avoid multiple processes regenerating value
     */
    public function lock(
        DateInterval|string|Stringable|int|null $ttl = null
    ): bool {
        $this->locked = true;

        if ($ttl !== null) {
            $date = new Carbon();
            $date->add(Coercion::asDateInterval($ttl));
            $expires = $date->getTimestamp();
        } else {
            $expires = time() + static::LockTTL;
        }

        return $this->store->getDriver()->storeLock(
            $this->store->getNamespace(),
            $this->key,
            $expires
        );
    }

    /**
     * Remove lock entry
     */
    public function unlock(): void
    {
        if (!$this->locked) {
            return;
        }

        $this->store->getDriver()->deleteLock(
            $this->store->getNamespace(),
            $this->key
        );
    }

    /**
     * Store item to driver
     */
    public function save(): bool
    {
        $this->ensureFetched();

        if ($this->locked) {
            $this->store->getDriver()->deleteLock(
                $this->store->getNamespace(),
                $this->key
            );

            $this->locked = false;
        }

        $created = time();
        $expires = null;

        if ($this->expiration) {
            $expires = $this->expiration->getTimestamp();
            $expires -= rand(0, (int)floor(($expires - $created) * 0.15));
        }

        $ttl = null;

        if ($expires) {
            $ttl = $expires - $created;

            if ($ttl < 0) {
                $this->delete();
                return false;
            }
        }

        return $this->store->getDriver()->store(
            $this->store->getNamespace(),
            $this->key,
            $this->value,
            $created,
            $expires
        );
    }

    /**
     * Defer saving until commit on pool
     */
    public function defer(): bool
    {
        return $this->store->saveDeferred($this);
    }

    /**
     * Set value and save
     *
     * @param T $value
     */
    public function update(
        mixed $value,
        DateTimeInterface|DateInterval|string|int|null $ttl = null
    ): bool {
        if ($ttl) {
            $this->setExpiration($ttl);
        }

        $this->set($value);
        return $this->save();
    }

    /**
     * Re-store item
     */
    public function extend(
        DateTimeInterface|DateInterval|string|int|null $ttl = null
    ): bool {
        if ($ttl) {
            $this->setExpiration($ttl);
        }

        if (null !== ($value = $this->get())) {
            $this->set($value);
        }

        return $this->save();
    }

    /**
     * Delete current item
     */
    public function delete(): bool
    {
        $output = $this->store->getDriver()->delete(
            $this->store->getNamespace(),
            $this->key
        );

        if ($output) {
            $this->value = null;
            $this->hit = false;
        }

        return $output;
    }


    /**
     * Ensure data has been fetched from driver
     */
    protected function ensureFetched(): void
    {
        if ($this->fetched) {
            return;
        }

        $time = time();
        $driver = $this->store->getDriver();

        $res = $driver->fetch(
            $this->store->getNamespace(),
            $this->key
        );

        if (!$res) {
            $this->hit = false;
            $this->value = null;
        } else {
            $this->hit = true;
            $this->value = $res[0];

            if ($res[1] === null) {
                $this->expiration = null;
            } else {
                $this->expiration = Carbon::createFromTimestamp($res[1]);
            }

            if (
                $this->expiration &&
                $this->expiration->getTimestamp() < $time
            ) {
                $this->hit = false;
                $this->value = null;

                $driver->delete(
                    $this->store->getNamespace(),
                    $this->key
                );
            }
        }

        $this->fetched = true;
        $policy = $this->getPileUpPolicy();

        if ($policy === PileUpPolicy::Ignore) {
            return;
        }

        $ttl = $this->expiration ? $this->expiration->getTimestamp() - $time : null;

        if ($this->hit) {
            if (
                $policy === PileUpPolicy::Preempt &&
                $ttl > 0 &&
                $ttl < $this->getPreemptTime()
            ) {
                $lockExp = $driver->fetchLock(
                    $this->store->getNamespace(),
                    $this->key
                );

                if ($lockExp < $time) {
                    $lockExp = null;
                }

                if (!$lockExp) {
                    $this->hit = false;
                    $this->value = null;
                }
            }

            return;
        }

        $lockExp = $driver->fetchLock(
            $this->store->getNamespace(),
            $this->key
        );

        if (!$lockExp) {
            return;
        }

        if ($policy === PileUpPolicy::Sleep) {
            $options = [$policy, PileUpPolicy::Value];
        } else {
            $options = [$policy, PileUpPolicy::Sleep];
        }

        foreach ($options as $option) {
            switch ($option) {
                case PileUpPolicy::Value:
                    if ($this->fallbackValue !== null) {
                        $this->value = $this->fallbackValue;
                        $this->hit = true;
                        return;
                    }

                    break;

                case PileUpPolicy::Sleep:
                    $attempts = $this->getSleepAttempts();
                    $time = $this->getSleepTime();

                    while ($attempts > 0) {
                        usleep($time * 1000);
                        $attempts--;

                        $res = $driver->fetch(
                            $this->store->getNamespace(),
                            $this->key
                        );

                        if ($res) {
                            $this->hit = true;
                            $this->value = $res[0];

                            if ($res[1] === null) {
                                $this->expiration = null;
                            } else {
                                $this->expiration = Carbon::createFromTimestamp($res[1]);
                            }
                            return;
                        }
                    }

                    $this->hit = false;
                    $this->value = null;

                    break;
            }
        }
    }
}
