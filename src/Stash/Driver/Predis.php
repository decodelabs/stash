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
use Predis\Client;
use Predis\ClientInterface;

class Predis implements Driver
{
    use IndexedKeyGenTrait;

    protected ClientInterface $client;

    public static function isAvailable(): bool
    {
        return class_exists(Client::class);
    }

    public function __construct(
        Stash $context,
        array $settings
    ) {
        $this->generatePrefix(
            Coercion::tryString($settings['prefix'] ?? null)
        );

        $this->client = new Client($settings);
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
            return 'OK' === $this->client->setex($key, $ttl, $data)->getPayload();
        } else {
            return 'OK' === $this->client->set($key, $data)?->getPayload();
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

        /** @var array{0: mixed, 1: ?int}|null */
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
        return 'OK' === $this->client->setex($key, $expires, $expires - time())->getPayload();
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

    public function getKeys(
        string $namespace
    ): array {
        /** @var array<string> */
        $output = $this->client->keys($this->prefix . ':*');
        return $output;
    }

    protected function getPathIndex(
        string $pathKey
    ): int {
        return (int)$this->client->get($pathKey);
    }

    public function purge(): void
    {
        $this->client->flushdb();
    }
}
