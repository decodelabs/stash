<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Coercion;
use DecodeLabs\Stash;
use DecodeLabs\Stash\Driver;
use Redis as Client;
use RedisException;

class Redis implements Driver
{
    use IndexedKeyGenTrait;

    protected Client $client;

    public static function isAvailable(): bool
    {
        return extension_loaded('redis');
    }

    public function __construct(
        Stash $context,
        array $settings
    ) {
        $this->generatePrefix(
            Coercion::tryString($settings['prefix'] ?? null)
        );

        $host = Coercion::tryString($settings['host'] ?? null) ?? '127.0.0.1';
        $port = Coercion::tryInt($settings['port'] ?? null) ?? 6379;
        $timeout = Coercion::tryFloat($settings['timeout'] ?? null) ?? 0;

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

    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return $this->client->setex($key, $expires, (string)($expires - time()));
    }

    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        $key = $this->createLockKey($namespace, $key);
        $output = $this->client->get($key);
        return Coercion::tryInt($output);
    }

    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return (bool)$this->client->del($key);
    }

    public function count(
        string $namespace,
    ): int {
        return count($this->getKeys($namespace));
    }

    public function getKeys(string $namespace): array
    {
        return $this->client->keys($this->prefix . ':*');
    }

    protected function getPathIndex(
        string $pathKey
    ): int {
        return Coercion::asInt($this->client->get($pathKey));
    }

    public function purge(): void
    {
        $this->client->flushDb();
    }
}
