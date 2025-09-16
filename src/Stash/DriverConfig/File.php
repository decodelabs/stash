<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\DriverConfig;

use DecodeLabs\Stash\Driver\File as FileDriver;
use DecodeLabs\Stash\Driver\PhpFile as PhpFileDriver;
use DecodeLabs\Stash\DriverConfig;

class File implements DriverConfig
{
    public array $driverClasses {
        get => [
            PhpFileDriver::class,
            FileDriver::class
        ];
    }

    public function __construct(
        public string $name,
        public ?string $prefix = null,
        public ?string $path = null,
        public ?int $dirPermissions = null,
        public ?int $filePermissions = null,
    ) {
    }
}
