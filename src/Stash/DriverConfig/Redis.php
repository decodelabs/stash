<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\DriverConfig;

use DecodeLabs\Stash\Driver\Predis as PredisDriver;
use DecodeLabs\Stash\Driver\Redis as RedisDriver;
use DecodeLabs\Stash\DriverConfig;

class Redis implements DriverConfig
{
    public array $driverClasses {
        get => [
            RedisDriver::class,
            PredisDriver::class
        ];
    }

    public function __construct(
        public string $name,
        public ?string $host = null,
        public ?int $port = null,
        public ?int $timeout = null,
        public ?string $prefix = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?int $database = null,

        /**
         * @var ?array<string,mixed>
         */
        public ?array $sslOptions = null,
    ) {
        if ($this->host === null) {
            $this->host = '127.0.0.1';
        }

        if ($this->isSocket()) {
            $this->port = -1;
        } elseif ($this->port === null) {
            $this->port = 6379;
        }
    }

    public function isSocket(): bool
    {
        return str_contains($this->host ?? '', '/');
    }
}
