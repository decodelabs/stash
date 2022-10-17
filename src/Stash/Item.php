<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use DateTimeInterface;
use DateInterval;

use DecodeLabs\Coercion;
use DecodeLabs\Exceptional;
use DecodeLabs\Stash\PileUpPolicy;
use DecodeLabs\Stash\Store;

use Psr\Cache\CacheItemInterface as CacheItem;

use Stringable;

class Item implements CacheItem
{
    public const LOCK_TTL = 30;

    protected string $key;
    protected mixed $value;
    protected bool $isHit = false;
    protected bool $fetched = false;

    protected ?Carbon $expiration;
    protected bool $locked = false;

    /**
     * @phpstan-var value-of<PileUpPolicy::KEYS>|null
     */
    protected ?string $pileUpPolicy = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected ?int $preemptTime = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected ?int $sleepTime = null;

    /**
     * @phpstan-var positive-int|null
     */
    protected ?int $sleepAttempts = null;

    protected mixed $fallbackValue = null;
    protected Store $store;

    /**
     * Init with store and key
     */
    public function __construct(
        Store $store,
        string $key
    ) {
        $this->key = $key;
        $this->store = $store;
    }

    /**
     * Get parent store
     */
    public function getStore(): Store
    {
        return $this->store;
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
     * @return $this
     */
    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;
        $this->fetched = true;
        return $this;
    }

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
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

        if (!$this->isHit) {
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

        $this->expiration = Carbon::instance(Coercion::toDateTime($expiration));
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
        $date->add(Coercion::toDateInterval($time));

        $this->expiration = $date;
        return $this;
    }


    /**
     * Work out best expiration from value
     *
     * @return $this;
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
            return $this->expiresAfter($expiration);
        } else {
            return $this->expiresAt($expiration);
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
        $this->pileUpPolicy = PileUpPolicy::IGNORE;
        return $this;
    }

    /**
     * Set pile up policy to preempt
     *
     * @param positive-int|null $time
     * @return $this
     */
    public function pileUpPreempt(int $time=null): static
    {
        $this->pileUpPolicy = PileUpPolicy::PREEMPT;

        if ($time !== null) {
            $this->preemptTime = $time;
        }

        return $this;
    }

    /**
     * Set pile up policy to sleep
     *
     * @param positive-int|null $time
     * @param positive-int|null $attempts
     * @return $this
     */
    public function pileUpSleep(
        int $time=null,
        int $attempts=null
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
     *
     * @return $this
     */
    public function pileUpValue(mixed $value): static
    {
        $this->pileUpPolicy = PileUpPolicy::VALUE;
        $this->fallbackValue = $value;
        return $this;
    }


    /**
     * Set pile up policy
     *
     * @phpstan-param value-of<PileUpPolicy::KEYS> $policy
     * @return $this
     */
    public function setPileUpPolicy(string $policy): static
    {
        $this->pileUpPolicy = $policy;
        return $this;
    }

    /**
     * Get pile up policy
     *
     * @phpstan-return value-of<PileUpPolicy::KEYS>
     */
    public function getPileUpPolicy(): string
    {
        return $this->pileUpPolicy ?? $this->store->getPileUpPolicy();
    }


    /**
     * Replace preempt time
     *
     * @param positive-int $time
     * @return $this
     */
    public function setPreemptTime(int $time): static
    {
        $this->preemptTime = $time;
        return $this;
    }

    /**
     * Get preempt time
     *
     * @return positive-int
     */
    public function getPreemptTime(): int
    {
        return $this->preemptTime ?? $this->store->getPreemptTime();
    }


    /**
     * Replace sleep time
     *
     * @param positive-int $time
     * @return $this
     */
    public function setSleepTime(int $time): static
    {
        $this->sleepTime = $time;
        return $this;
    }

    /**
     * Get sleep time
     *
     * @return positive-int
     */
    public function getSleepTime(): int
    {
        return $this->sleepTime ?? $this->store->getSleepTime();
    }

    /**
     * Replace sleep attempts
     *
     * @param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(int $attempts): static
    {
        $this->sleepAttempts = $attempts;
        return $this;
    }

    /**
     * Get sleep attempts
     *
     * @return positive-int
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
    public function setFallbackValue(mixed $value): static
    {
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
            $date->add(Coercion::toDateInterval($ttl));
            $expires = $date->getTimestamp();
        } else {
            $expires = time() + static::LOCK_TTL;
        }

        return $this->store->getDriver()->storeLock(
            $this->store->getNamespace(),
            $this->key,
            $expires
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
     */
    public function update(
        mixed $value,
        DateTimeInterface|DateInterval|string|int|null $ttl=null
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
        DateTimeInterface|DateInterval|string|int|null $ttl=null
    ): bool {
        if ($ttl) {
            $this->setExpiration($ttl);
        }

        $this->set($this->get());
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
            $this->isHit = false;
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
            $this->isHit = false;
            $this->value = null;
        } else {
            $this->isHit = true;
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
                $this->isHit = false;
                $this->value = null;

                $driver->delete(
                    $this->store->getNamespace(),
                    $this->key
                );
            }
        }

        $this->fetched = true;
        $policy = $this->getPileUpPolicy();

        if ($policy === PileUpPolicy::IGNORE) {
            return;
        }

        $ttl = $this->expiration ? $this->expiration->getTimestamp() - $time : null;

        if ($this->isHit) {
            if (
                $policy === PileUpPolicy::PREEMPT &&
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
                    $this->isHit = false;
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

        $options = array_unique([$policy, PileUpPolicy::VALUE, PileUpPolicy::SLEEP]);

        foreach ($options as $option) {
            switch ($option) {
                case PileUpPolicy::VALUE:
                    if ($this->fallbackValue !== null) {
                        $this->value = $this->fallbackValue;
                        $this->isHit = true;
                        return;
                    }

                    break;

                case PileUpPolicy::SLEEP:
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
                            $this->isHit = true;
                            $this->value = $res[0];

                            if ($res[1] === null) {
                                $this->expiration = null;
                            } else {
                                $this->expiration = Carbon::createFromTimestamp($res[1]);
                            }
                            return;
                        }
                    }

                    $this->isHit = false;
                    $this->value = null;

                    break;
            }
        }
    }
}
