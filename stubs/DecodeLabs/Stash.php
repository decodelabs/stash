<?php
/**
 * This is a stub file for IDE compatibility only.
 * It should not be included in your projects.
 */
namespace DecodeLabs;

use DecodeLabs\Veneer\Proxy as Proxy;
use DecodeLabs\Veneer\ProxyTrait as ProxyTrait;
use DecodeLabs\Stash\Context as Inst;
use DecodeLabs\Stash\Config as Ref0;
use DecodeLabs\Stash\Store as Ref1;
use DecodeLabs\Stash\Driver as Ref2;

class Stash implements Proxy
{
    use ProxyTrait;

    const VENEER = 'DecodeLabs\\Stash';
    const VENEER_TARGET = Inst::class;
    const DRIVERS = Inst::DRIVERS;

    public static Inst $instance;

    public static function setConfig(?Ref0 $config): void {}
    public static function getConfig(): ?Ref0 {
        return static::$instance->getConfig();
    }
    public static function setDefaultPrefix(?string $prefix): Inst {
        return static::$instance->setDefaultPrefix(...func_get_args());
    }
    public static function getDefaultPrefix(): ?string {
        return static::$instance->getDefaultPrefix();
    }
    public static function load(string $namespace): Ref1 {
        return static::$instance->load(...func_get_args());
    }
    public static function loadDriverFor(string $namespace): Ref2 {
        return static::$instance->loadDriverFor(...func_get_args());
    }
    public static function loadDriver(string $name): ?Ref2 {
        return static::$instance->loadDriver(...func_get_args());
    }
    public static function purgeAll(): void {}
    public static function purge(string $name): void {}
};
