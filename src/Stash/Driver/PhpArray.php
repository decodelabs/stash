<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Coercion;
use DecodeLabs\Stash\Driver;

class PhpArray implements Driver
{
    use KeyGenTrait;

    /**
     * @var array<string,array<mixed>>
     * @phpstan-var array<string,array{0: mixed, 1: ?int}>
     */
    protected array $values = [];

    /**
     * @var array<string,array<string,int>>
     */
    protected array $locks = [];


    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return true;
    }


    /**
     * Init with settings
     */
    public function __construct(
        array $settings
    ) {
        $this->generatePrefix(
            Coercion::tryString($settings['prefix'] ?? null)
        );
    }


    /**
     * Store item data
     */
    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool {
        $this->values[$this->createKey($namespace, $key)] = [
            $value, $expires
        ];

        return true;
    }

    /**
     * Fetch item data
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        return $this->values[$this->createKey($namespace, $key)] ?? null;
    }

    /**
     * Remove item from store
     */
    public function delete(
        string $namespace,
        string $key
    ): bool {
        $regex = $this->createRegexKey($namespace, $key);

        foreach ($this->values as $key => $value) {
            if (preg_match($regex, $key)) {
                unset($this->values[$key]);
            }
        }

        return true;
    }

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        $regex = $this->createRegexKey($namespace, null);

        foreach ($this->values as $key => $value) {
            if (preg_match($regex, $key)) {
                unset($this->values[$key]);
            }
        }

        unset($this->locks[$namespace]);
        return true;
    }



    /**
     * Save a lock for a key
     */
    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $this->locks[$namespace][$key] = $expires;
        return true;
    }

    /**
     * Get a lock expiry for a key
     */
    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        return $this->locks[$namespace][$key] ?? null;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        unset($this->locks[$namespace][$key]);
        return true;
    }


    /**
     * Count items
     */
    public function count(
        string $namespace
    ): int {
        return count($this->getKeys($namespace));
    }


    /**
     * Get keys
     */
    public function getKeys(
        string $namespace
    ): array {
        $output = [];
        $prefix = $this->prefix . $this->getKeySeparator() . $namespace . $this->getKeySeparator();

        foreach ($this->values as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $output[] = $key;
            }
        }

        return $output;
    }


    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        $this->values = [];
        $this->locks = [];
    }
}
