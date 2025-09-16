<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

interface DriverConfig
{
    public string $name { get; }
    public ?string $prefix { get; }


    /**
     * @var list<class-string<Driver>>
     */
    public array $driverClasses { get; }
}
