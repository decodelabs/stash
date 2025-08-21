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

    public protected(set) string $key;

    /**
     * @var ?T
     */
    public protected(set) mixed $value;

    public protected(set) bool $hit = false;
    protected bool $fetched = false;

    public protected(set) ?Carbon $expiration = null;
    public protected(set) bool $locked = false;

    public protected(set) ?PileUpPolicy $pileUpPolicy = null;

    /**
     * @var positive-int|null
     */
    public protected(set) ?int $preemptTime = null;

    /**
     * @var positive-int|null
     */
    public protected(set) ?int $sleepTime = null;

    /**
     * @var positive-int|null
     */
    public protected(set) ?int $sleepAttempts = null;

    public protected(set) mixed $fallbackValue = null;

    /**
     * @var Store<T>
     */
    public protected(set) Store $store;

    /**
     * @param Store<T> $store
     */
    public function __construct(
        Store $store,
        string $key
    ) {
        $this->key = $key;
        $this->store = $store;
    }


    public function getKey(): string
    {
        return $this->key;
    }

    /**
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
     * @return ?T
     */
    public function get(): mixed
    {
        if (!$this->isHit()) {
            return null;
        }

        return $this->value;
    }

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

    public function isMiss(): bool
    {
        return !$this->isHit();
    }

    /**
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


    public function getExpiration(): ?Carbon
    {
        return $this->expiration;
    }

    public function getExpirationTimestamp(): ?int
    {
        if (!$this->expiration) {
            return null;
        }

        return $this->expiration->getTimestamp();
    }


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
     * @return $this
     */
    public function pileUpIgnore(): static
    {
        $this->pileUpPolicy = PileUpPolicy::Ignore;
        return $this;
    }

    /**
     * @param positive-int|null $time
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
     * @param positive-int|null $time
     * @param positive-int|null $attempts
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
     * @return $this
     */
    public function setPileUpPolicy(
        PileUpPolicy $policy
    ): static {
        $this->pileUpPolicy = $policy;
        return $this;
    }

    public function getPileUpPolicy(): PileUpPolicy
    {
        return $this->pileUpPolicy ?? $this->store->getPileUpPolicy();
    }


    /**
     * @param positive-int $time
     * @return $this
     */
    public function setPreemptTime(
        int $time
    ): static {
        $this->preemptTime = $time;
        return $this;
    }

    /**
     * @return positive-int
     */
    public function getPreemptTime(): int
    {
        return $this->preemptTime ?? $this->store->getPreemptTime();
    }


    /**
     * @param positive-int $time
     * @return $this
     */
    public function setSleepTime(
        int $time
    ): static {
        $this->sleepTime = $time;
        return $this;
    }

    /**
     * @return positive-int
     */
    public function getSleepTime(): int
    {
        return $this->sleepTime ?? $this->store->getSleepTime();
    }

    /**
     * @param positive-int $attempts
     * @return $this
     */
    public function setSleepAttempts(
        int $attempts
    ): static {
        $this->sleepAttempts = $attempts;
        return $this;
    }

    /**
     * @return positive-int
     */
    public function getSleepAttempts(): int
    {
        return $this->sleepAttempts ?? $this->store->getSleepAttempts();
    }


    /**
     * @return $this
     */
    public function setFallbackValue(
        mixed $value
    ): static {
        $this->fallbackValue = $value;
        return $this;
    }

    public function getFallbackValue(): mixed
    {
        return $this->fallbackValue;
    }


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

    public function defer(): bool
    {
        return $this->store->saveDeferred($this);
    }

    /**
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
