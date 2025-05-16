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
use DecodeLabs\Stash\FileStore as Ref3;
use DateInterval as Ref4;
use Stringable as Ref5;

class Stash implements Proxy
{
    use ProxyTrait;

    public const Veneer = 'DecodeLabs\\Stash';
    public const VeneerTarget = Inst::class;

    protected static Inst $_veneerInstance;

    public static function setConfig(?Ref0 $config): void {}
    public static function getConfig(): ?Ref0 {
        return static::$_veneerInstance->getConfig();
    }
    public static function setDefaultPrefix(?string $prefix): Inst {
        return static::$_veneerInstance->setDefaultPrefix(...func_get_args());
    }
    public static function getDefaultPrefix(): ?string {
        return static::$_veneerInstance->getDefaultPrefix();
    }
    public static function load(string $namespace): Ref1 {
        return static::$_veneerInstance->load(...func_get_args());
    }
    public static function loadStealth(string $namespace): Ref1 {
        return static::$_veneerInstance->loadStealth(...func_get_args());
    }
    public static function loadDriverFor(string $namespace, bool $stealth = false): Ref2 {
        return static::$_veneerInstance->loadDriverFor(...func_get_args());
    }
    public static function loadDriver(string $name, bool $stealth = false): ?Ref2 {
        return static::$_veneerInstance->loadDriver(...func_get_args());
    }
    public static function purge(): void {}
    public static function purgeDriver(string $name): void {}
    public static function loadFileStore(string $namespace): Ref3 {
        return static::$_veneerInstance->loadFileStore(...func_get_args());
    }
    public static function pruneFileStores(Ref4|Ref5|string|int $duration): int {
        return static::$_veneerInstance->pruneFileStores(...func_get_args());
    }
    public static function purgeFileStores(): void {}
};
