<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\DriverConfig;

use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\Driver\Apcu as ApcuDriver;
use DecodeLabs\Stash\Driver\BlackHole as BlackHoleDriver;
use DecodeLabs\Stash\Driver\PhpArray as PhpArrayDriver;
use DecodeLabs\Stash\Driver\PhpFile as PhpFileDriver;
use DecodeLabs\Stash\DriverConfig;

class Fallback implements DriverConfig
{
    private const Drivers = [
        ApcuDriver::class,
        PhpFileDriver::class,
        PhpArrayDriver::class,
        BlackHoleDriver::class,
    ];

    public array $driverClasses {
        get {
            $output = self::Drivers;

            if (in_array($this->prefer, $output)) {
                array_unshift($output, $this->prefer);
                $output = array_unique($output);
            }

            return $output;
        }
    }

    public function __construct(
        public string $name,
        public ?string $prefix = null,
        public ?string $prefer = null
    ) {
        if (
            $this->prefer !== null &&
            !str_contains($this->prefer, '\\')
        ) {
            $this->prefer = Driver::class . '\\' . $this->prefer;
        }
    }
}
