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

$stash = new Stash();
$myCache = $stash->load('MyCache');

if(!$myCache->has('myValue')) {
    $myCache->set('myValue', [1, 2, 3]);
}

$total = 0;

foreach($myCache->get('myValue', []) as $number) {
    $total += $number;
}

$myCache->delete('myValue');
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

Stash uses [Archetype](https://github.com/decodelabs/archetype) to load driver classes so additional drivers may be provided by implementing your own `Resolver`.

If no configuration is provided, Stash will attempt to use the best-fit driver for your environment, starting with Memcache, through Redis and APCu, and falling back on the File store.

## Configuration

Stash can be configured by passing a `DriverManager` to the constructor (or in your DI container):

```php
use DecodeLabs\Stash\DriverManager;

$stash = new Stash(new DriverManager(
    new RedisConfig('localRedis'),
    new RedisConfig('anotherRedis', host: '123.456.789.000', port: 6379),
    new MemcacheConfig('remoteMemcache', host: '123.456.789.000', port: 11211),
    new NamespaceConfig('MyStore', driver: 'remoteMemcache'),
    new FileStoreConfig('specialFileStore', path: '/tmp/stash/fileStore'),
));
```
You may pass any number of `DriverConfig`, `NamespaceConfig` and `FileStoreConfig` objects to the `DriverManager` to configure the drivers and stores - the `DriverManager` will use the most relevant driver for each namespace.

If you're using `Kingdom` to manage your application, it is advisable to provide a `DriverManager` to your container and specify the drivers you want to use during initialization.

```php
use DecodeLabs\Fabric\Kingdom as FabricKingdom;
use DecodeLabs\Kingdom\ContainerAdapter;
use DecodeLabs\Stash\DriverManager;
use DecodeLabs\Stash\DriverConfig\Redis as RedisConfig;

class Kingdom extends FabricKingdom
{
    public function initialize(): void
    {
        parent::initialize();

        $this->container->setFactory(
            DriverManager::class,
            fn () => new DriverManager(
                new RedisConfig('localRedis')
            )
        );
    }
}
```

## Custom Store methods

By default, newly loaded caches use a generic Store implementation, however if you require custom methods for domain-oriented data access, you can implement your own Store classes using a custom [Archetype](https://github.com/decodelabs/archetype) `Resolver`.

```php
namespace MyApp;

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

$archetype->map(Store::class, namespace::class);

$myCache = $stash->load('MyCache'); // Will now use MyApp\MyCache
```


## Licensing
Stash is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
