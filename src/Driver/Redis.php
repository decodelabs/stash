<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Coercion;
use DecodeLabs\Stash\Driver;

use Redis as Client;
use RedisException;

class Redis implements Driver
{
    use IndexedKeyGenTrait;

    protected Client $client;

    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('redis');
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

        $host = Coercion::toStringOrNull($settings['host'] ?? null) ?? '127.0.0.1';
        $port = Coercion::toIntOrNull($settings['port'] ?? null) ?? 6379;
        $timeout = Coercion::toFloatOrNull($settings['timeout'] ?? null) ?? 0;

        $client = new Client();
        $client->connect($host, $port, $timeout);

        $this->client = $client;
    }

    /**
     * Ensure redis is closed
     */
    public function __destruct()
    {
        try {
            $this->client->close();
        } catch (RedisException $e) {
        }
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

        $key = $this->createNestedKey($namespace, $key)[0];
        $data = serialize([$value, $expires]);

        if ($ttl > 0) {
            return $this->client->setex($key, $ttl, $data);
        } else {
            return $this->client->set($key, $data);
        }
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

        if (is_string($output)) {
            $output = unserialize($output);
        } else {
            $output = null;
        }

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
            $this->client->del($key[0]);
        }

        if ($man['children']) {
            if (!$this->client->incr($key[1])) {
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

        if (!$this->client->incr($key)) {
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
        return $this->client->setex($key, $expires, (string)($expires - time()));
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
        return Coercion::toIntOrNull($output);
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return (bool)$this->client->del($key);
    }



    /**
     * Get cached path index
     */
    protected function getPathIndex(
        string $pathKey
    ): int {
        return (int)$this->client->get($pathKey);
    }


    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        $this->client->flushDb();
    }
}
