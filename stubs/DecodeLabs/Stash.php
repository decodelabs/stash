<?php
/**
 * This is a stub file for IDE compatibility only.
 * It should not be included in your projects.
 */
namespace DecodeLabs;

use DecodeLabs\Veneer\Proxy;
use DecodeLabs\Veneer\ProxyTrait;
use DecodeLabs\Stash\Context as Inst;
use DecodeLabs\Stash\Config as Ref0;
use DecodeLabs\Stash\Store as Ref1;
use DecodeLabs\Stash\Driver as Ref2;

class Stash implements Proxy
{
    use ProxyTrait;

    const VENEER = 'DecodeLabs\Stash';
    const VENEER_TARGET = Inst::class;
    const DRIVERS = Inst::DRIVERS;

    public static Inst $instance;

    public static function setConfig(?Ref0 $config): void {}
    public static function getConfig(): ?Ref0 {
        return static::$instance->getConfig();
    }
    public static function get(string $namespace): Ref1 {
        return static::$instance->get(...func_get_args());
    }
    public static function getDriverFor(string $namespace): Ref2 {
        return static::$instance->getDriverFor(...func_get_args());
    }
    public static function loadDriver(string $name): ?Ref2 {
        return static::$instance->loadDriver(...func_get_args());
    }
    public static function purgeAll(): void {}
    public static function purge(string $name): void {}
};
