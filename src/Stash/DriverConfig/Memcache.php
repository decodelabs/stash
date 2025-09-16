<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\DriverConfig;

use DecodeLabs\Stash\Driver\Memcache as MemcacheDriver;
use DecodeLabs\Stash\DriverConfig;

class Memcache implements DriverConfig
{
    public array $driverClasses {
        get => [
            MemcacheDriver::class,
        ];
    }

    /**
     * @param array<string> $servers
     */
    public function __construct(
        public string $name,
        ?string $host = null,
        ?int $port = null,
        public array $servers = [],
        public ?string $prefix = null,
    ) {
        if (empty($this->servers)) {
            $this->addServer($host ?? '127.0.0.1', $port ?? 11211);
        }
    }

    public function addServer(
        string $host,
        int $port
    ): void {
        $this->servers[] = $host . ':' . $port;
    }
}
