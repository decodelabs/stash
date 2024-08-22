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

    const Veneer = 'DecodeLabs\\Stash';
    const VeneerTarget = Inst::class;

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
    public static function purge(): void {}
    public static function purgeDriver(string $name): void {}
    public static function loadFileStore(string $namespace): Ref3 {
        return static::$instance->loadFileStore(...func_get_args());
    }
    public static function pruneFileStores(Ref4|Ref5|string|int $duration): int {
        return static::$instance->pruneFileStores(...func_get_args());
    }
    public static function purgeFileStores(): void {}
};
