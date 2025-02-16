<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Coercion;
use DecodeLabs\Stash\Driver;
use Memcached as Client;

class Memcache implements Driver
{
    use IndexedKeyGenTrait;

    protected Client $client;

    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('memcached');
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

        $client = new Client();

        if (is_array($settings['servers'] ?? null)) {
            $client->addServers($settings['servers']);
        } else {
            $host = Coercion::toStringOrNull($settings['host'] ?? null) ?? '127.0.0.1';
            $port = Coercion::toIntOrNull($settings['port'] ?? null) ?? 11211;

            $client->addServer($host, $port);
        }

        $this->client = $client;
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
        $key = $this->createNestedKey($namespace, $key)[0];
        return $this->client->set($key, [$value, $expires], $expires ?? 0);
    }

    /**
     * Fetch item data
     */
    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        $key = $this->createNestedKey($namespace, $key)[0];
        $output = $this->client->get($key);

        if (!is_array($output)) {
            $output = null;
        }

        /** @var array{0: mixed, 1: ?int} */
        return $output;
    }

    /**
     * Remove item from store
     */
    public function delete(
        string $namespace,
        string $key
    ): bool {
        $man = $this->parseKey($namespace, $key);
        $key = $this->createNestedKey($namespace, $man['normal']);

        if ($man['self']) {
            $this->client->delete($key[0]);
        }

        if ($man['children']) {
            if (!$this->client->increment($key[1])) {
                $this->client->set($key[1], 1);
            }
        }

        $this->keyCache = [];
        return true;
    }

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        $key = $this->createNestedKey($namespace, null)[1];

        if (!$this->client->increment($key)) {
            $this->client->set($key, 1);
        }

        $this->keyCache = [];
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
        $key = $this->createLockKey($namespace, $key);
        return $this->client->set($key, $expires, $expires);
    }

    /**
     * Get a lock expiry for a key
     */
    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        $key = $this->createLockKey($namespace, $key);
        $output = $this->client->get($key);

        if (!is_int($output)) {
            $output = null;
        }

        return $output;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return $this->client->delete($key);
    }


    /**
     * Count items
     */
    public function count(
        string $namespace,
    ): int {
        $output = 0;

        /** @var array<string>|null */
        $keys = $this->client->getAllKeys();

        if (!is_iterable($keys)) {
            return 0;
        }

        foreach ($keys as $key) {
            if (str_starts_with($key, $this->prefix)) {
                $output++;
            }
        }

        return $output;
    }

    /**
     * Get key
     */
    public function getKeys(
        string $namespace
    ): array {
        $output = [];

        /** @var array<string>|null */
        $keys = $this->client->getAllKeys();

        if (!is_iterable($keys)) {
            return [];
        }

        foreach ($keys as $key) {
            if (str_starts_with($key, $this->prefix)) {
                $output[] = $key;
            }
        }

        return $output;
    }


    /**
     * Get cached path index
     */
    protected function getPathIndex(
        string $pathKey
    ): int {
        return Coercion::toIntOrNull($this->client->get($pathKey)) ?? 0;
    }



    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        $this->client->flush();
    }
}
