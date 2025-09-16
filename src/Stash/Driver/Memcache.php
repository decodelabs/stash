<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Coercion;
use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\DriverConfig\Memcache as MemcacheConfig;
use Memcached as Client;

class Memcache implements Driver
{
    use IndexedKeyGenTrait;

    protected Client $client;

    public static function isAvailable(): bool
    {
        return extension_loaded('memcached');
    }

    public function __construct(
        MemcacheConfig $config
    ) {
        $this->generatePrefix($config->prefix);
        $client = new Client();

        foreach ($config->servers as $server) {
            [$host, $port] = explode(':', $server);
            $client->addServer($host, Coercion::asInt($port));
        }

        $this->client = $client;
    }

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


    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return $this->client->set($key, $expires, $expires);
    }

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

    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $key = $this->createLockKey($namespace, $key);
        return $this->client->delete($key);
    }

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

    protected function getPathIndex(
        string $pathKey
    ): int {
        return Coercion::tryInt($this->client->get($pathKey)) ?? 0;
    }

    public function purge(): void
    {
        $this->client->flush();
    }
}
