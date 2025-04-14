# Stash

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/stash?style=flat)](https://packagist.org/packages/decodelabs/stash)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/stash.svg?style=flat)](https://packagist.org/packages/decodelabs/stash)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/stash.svg?style=flat)](https://packagist.org/packages/decodelabs/stash)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/stash/integrate.yml?branch=develop)](https://github.com/decodelabs/stash/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/stash?style=flat)](https://packagist.org/packages/decodelabs/stash)

### Cache storage system

Stash provides a PSR6 / PSR16 compatible cache system for PHP.

---

## Installation

```bash
composer require decodelabs/stash
```

## Usage

Store and access data in a standardised volatile cache either via the PSR6 or PSR16 interface mechanisms.
Caches are namespaced to allow for clean separation of data between usage domains.

```php
use DecodeLabs\Stash;

$myCache = Stash::load('MyCache');

if(!$cache->has('myValue')) {
    $cache->set('myValue', [1, 2, 3]);
}

$total = 0;

foreach($cache->get('myValue', []) as $number) {
    $total += $number;
}

$cache->delete('myValue');
```

### Fetch
Use the fetch method to ensure a cache value is in place in one call:

```php
$myValue = $myCache->fetch('myValue', function() {
    return [1, 2, 3]; // Only called if key not found in cache
});
```

### Array Access
Array access methods provide quick offset access to cache data:

```php
if(!isset($myCache['myValue'])) {
    $myCache['myValue'] = 'Hello world';
}

echo $myCache['myValue'];
unset($MyCache['myValue']);
```

### Object access
Object access works the same way as ArrayAccess, but with the PSR6 Cache Item object as the return:

```php
$item = $myCache->myValue;

if(!$item->isHit()) {
    $item->set('Hello world');
}

echo $item->get();
$item->delete();
```

## Drivers

The following drivers are available out of the box:

- Memcache
- Redis
- Predis (native PHP redis client)
- APCu
- File (serialized data)
- PhpFile (var_export data)
- PhpArray (in memory)
- Blackhole (nothing stored)

However, Stash uses [Archetype](https://github.com/decodelabs/archetype) to load driver classes so additional drivers may be provided by implementing your own `Resolver`.

By default, Stash will use the best-fit driver for your environment, starting with Memcache, through Redis and APCu, and falling back on the File store.


### Configuration

All drivers have default configuration to allow them to work out of the box, however Stash provides the ability to implement your own configuration loader so that you can control drivers and settings on a per-namespace basis

Implement the following interface however your system requires; all nullable methods can just return null to use default configuration:

```php
interface Config
{
    public function getDriverFor(string $namespace): ?string;
    public function isDriverEnabled(string $driver): bool;
    public function getAllDrivers(): array;
    public function getDriverSettings(string $driver): ?array;

    public function getPileUpPolicy(string $namespace): ?PileUpPolicy;
    public function getPreemptTime(string $namespace): ?int;
    public function getSleepTime(string $namespace): ?int;
    public function getSleepAttempts(string $namespace): ?int;
}
```

Then tell Stash about your configuration provider:

```php
Stash::setConfig(new MyConfig());
```

## Custom Store methods

By default, newly loaded caches use a generic Store implementation, however if you require custom methods for domain-oriented data access, you can implement your own Store classes using a custom [Archetype](https://github.com/decodelabs/archetype) `Resolver`.

```php
namespace MyApp;

use DecodeLabs\Archetype;
use DecodeLabs\Stash\Store;
use DecodeLabs\Stash\Store\Generic;

class MyCache extends Generic
{

    public function getMyData(): string
    {
        return $this->fetch('myData', function() {
            return 'Hello world';
        });
    }
}

Archetype::map(Store::class, namespace::class);

$myCache = Stash::load('MyCache'); // Will now use MyApp\MyCache
```


## Licensing
Stash is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
