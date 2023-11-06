<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use APCuIterator;
use DecodeLabs\Coercion;
use DecodeLabs\Stash\Driver;

if (!defined('APC_ITER_KEY')) {
    define('APC_ITER_KEY', 2);
}

class Apcu implements Driver
{
    use KeyGenTrait;

    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('apcu');
    }

    /**
     * Init with settings
     */
    public function __construct(
        array $settings
    ) {
        $this->generatePrefix(
            Coercion::toStringOrNull($settings['prefix'] ?? null)
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
        if ($expires === null) {
            $ttl = 0;
        } else {
            $ttl = $expires - $created;
        }

        return apcu_store(
            $this->createKey($namespace, $key),
            [$value, $expires],
            $ttl
        );
    }

    /**
     * Fetch item data
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        $success = null;

        /** @var array{0: mixed, 1: ?int} */
        $output = apcu_fetch(
            $this->createKey($namespace, $key),
            $success
        );

        return $success ? $output : null;
    }

    /**
     * Remove item from store
     */
    public function delete(
        string $namespace,
        string $key
    ): bool {
        do {
            $empty = true;

            /** @var iterable<array<string, string>> $it */
            $it = new APCuIterator(
                $this->createRegexKey($namespace, $key),
                \APC_ITER_KEY,
                100
            );

            foreach ($it as $item) {
                $empty = false;
                apcu_delete($item['key']);
            }
        } while (!$empty);

        return true;
    }

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        do {
            $empty = true;

            /** @var iterable<array<string, string>> $it */
            $it = new APCuIterator(
                $this->createRegexKey($namespace, null),
                \APC_ITER_KEY,
                100
            );

            foreach ($it as $item) {
                $empty = false;
                apcu_delete($item['key']);
            }
        } while (!$empty);

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
        return apcu_store(
            $this->createLockKey($namespace, $key),
            $expires,
            $expires - time()
        );
    }

    /**
     * Get a lock expiry for a key
     */
    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        $success = null;

        /** @var ?int $output */
        $output = apcu_fetch(
            $this->createLockKey($namespace, $key),
            $success
        );

        return $success ? $output : null;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return (bool)apcu_delete($key);
    }


    /**
     * Count items
     */
    public function count(
        string $namespace
    ): int {
        $output = 0;
        $prefix = $this->prefix . $this->getKeySeparator() . $namespace . $this->getKeySeparator();

        foreach ($this->getCacheList() as $set) {
            if (str_starts_with($set['info'], $prefix)) {
                $output++;
            }
        }

        return $output;
    }


    /**
     * Get list of keys
     */
    public function getKeys(
        string $namespace
    ): array {
        $output = [];
        $prefix = $this->prefix . $this->getKeySeparator() . $namespace . $this->getKeySeparator();

        foreach ($this->getCacheList() as $set) {
            if (str_starts_with($set['info'], $prefix)) {
                $output[] = $set['info'];
            }
        }

        return $output;
    }


    /**
     * Get normalized APCU cache info
     *
     * @return array<int, array<string, mixed>>
     * @phpstan-return array<int, array{
     *      info: string
     * }>
     */
    public static function getCacheList(): array
    {
        $info = apcu_cache_info();
        $output = [];

        if (isset($info['cache_list'])) {
            /**
             * @var array<int, array<string, mixed>> $output
             * @phpstan-var array<int, array{
             *     info: string,
             *     key: string
             * }> $output
             */
            $output = $info['cache_list'];

            if (isset($output[0]['key'])) {
                foreach ($output as $i => $set) {
                    $key = $set['key'];
                    unset($set['key']);

                    $output[$i] = array_merge([
                        'info' => $key
                    ], $set);
                }
            }
        }

        return $output;
    }


    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        apcu_clear_cache();
    }
}
