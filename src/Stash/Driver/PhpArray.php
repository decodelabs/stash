<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\DriverConfig\Fallback as FallbackConfig;

class PhpArray implements Driver
{
    use KeyGenTrait;

    /**
     * @var array<string,array{0: mixed, 1: ?int}>
     */
    protected array $values = [];

    /**
     * @var array<string,array<string,int>>
     */
    protected array $locks = [];

    public static function isAvailable(): bool
    {
        return true;
    }

    public function __construct(
        ?FallbackConfig $config = null
    ) {
        $this->generatePrefix($config?->prefix);
    }

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

    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        return $this->values[$this->createKey($namespace, $key)] ?? null;
    }

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

    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $this->locks[$namespace][$key] = $expires;
        return true;
    }

    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        return $this->locks[$namespace][$key] ?? null;
    }

    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        unset($this->locks[$namespace][$key]);
        return true;
    }

    public function count(
        string $namespace
    ): int {
        return count($this->getKeys($namespace));
    }

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

    public function purge(): void
    {
        $this->values = [];
        $this->locks = [];
    }
}
