# Stash — Package Specification

> **Cluster:** `io`
> **Language:** `php`
> **Milestone:** `m3`
> **Repo:** `https://github.com/decodelabs/stash`
> **Role:** Caching

## Overview

### Purpose

Stash provides a PSR6 / PSR16 compatible cache system for PHP. It offers a standardized volatile cache interface with support for multiple storage backends (Memcache, Redis, APCu, File, etc.) and namespaced cache stores for clean separation of data between usage domains.

Key features:
- **PSR-6 and PSR-16 compliance**: Full implementation of PSR-6 Cache Item Pool and PSR-16 Simple Cache interfaces
- **Multiple drivers**: Support for Memcache, Redis, Predis, APCu, File, PhpFile, PhpArray, and Blackhole drivers
- **Namespaced stores**: Separate cache namespaces for different usage domains
- **Pile-up protection**: Configurable policies for handling concurrent cache generation (ignore, preempt, sleep, value)
- **Deferred saves**: Batch operations with deferred commit
- **Array and object access**: Convenient array and object property access to cache items
- **Fetch pattern**: Single-call cache-or-generate pattern
- **File stores**: Separate file-based storage system for larger or persistent data

### Non-Goals

- Stash does not provide distributed cache coordination or consensus mechanisms.
- It does not handle cache invalidation strategies beyond TTL expiration.
- It does not provide cache warming or preloading capabilities.
- It does not handle cache versioning or migration.
- It does not provide cache analytics or monitoring tools.

## Role in the Ecosystem

### Cluster & Positioning

Stash belongs to the **io** cluster, focusing on input/output operations. It complements other IO packages by providing caching capabilities for data access patterns.

### Usage Contexts

- **Application caching**: Caching application data, computed results, and expensive operations
- **Session storage**: Storing session data (via appropriate drivers)
- **Template caching**: Caching rendered templates and compiled views
- **Database query caching**: Caching database query results
- **API response caching**: Caching external API responses
- **File storage**: Storing files with TTL-based expiration

## Public Surface

### Key Types

- **`Stash`** (class): Main cache service providing namespace-based cache store loading. Implements `Service` for Kingdom integration.

- **`Store`** (interface): Cache store interface extending PSR-6 `CacheItemPoolInterface`, PSR-16 `CacheInterface`, `ArrayAccess`, and `Countable`. Provides cache operations for a namespace.

- **`Store\Generic`** (class): Generic store implementation providing PSR-6/PSR-16 compliance, deferred saves, and pile-up protection.

- **`Item`** (class): Cache item implementation implementing PSR-6 `CacheItemInterface`. Represents a single cache entry with expiration, locking, and pile-up handling.

- **`Driver`** (interface): Cache driver interface for storage backends. Defines methods for storing, fetching, deleting, locking, and purging.

- **`Driver\Memcache`** (class): Memcache driver implementation.

- **`Driver\Redis`** (class): Redis driver implementation.

- **`Driver\Predis`** (class): Predis (native PHP Redis client) driver implementation.

- **`Driver\Apcu`** (class): APCu driver implementation.

- **`Driver\File`** (class): File-based driver using serialized data.

- **`Driver\PhpFile`** (class): PHP file-based driver using `var_export` data.

- **`Driver\PhpArray`** (class): In-memory array driver.

- **`Driver\BlackHole`** (class): Blackhole driver that stores nothing.

- **`DriverManager`** (class): Manages driver configurations, namespace configurations, and driver instantiation.

- **`DriverConfig`** (interface): Driver configuration interface.

- **`DriverConfig\Redis`** (class): Redis driver configuration.

- **`DriverConfig\Memcache`** (class): Memcache driver configuration.

- **`DriverConfig\File`** (class): File driver configuration.

- **`DriverConfig\Fallback`** (class): Fallback driver configuration.

- **`NamespaceConfig`** (class): Namespace configuration specifying driver, pile-up policy, and timing settings.

- **`FileStore`** (interface): File-based storage interface extending `ArrayAccess` and `Countable`. Provides file storage with TTL-based expiration.

- **`FileStore\Generic`** (class): Generic file store implementation.

- **`FileStoreConfig`** (class): File store configuration.

- **`PileUpPolicy`** (enum): Pile-up handling policy: `Ignore`, `Preempt`, `Sleep`, `Value`.

### Main Entry Points

**Stash Service:**
- `new Stash(Archetype $archetype, DriverManager $driverManager)` — Constructor
- `$stash->load(string $namespace): Store` — Load cache store for namespace
- `$stash->purge(): void` — Purge all caches
- `$stash->loadFileStore(string $namespace): FileStore` — Load file store for namespace
- `$stash->pruneFileStores(DateInterval|string|Stringable|int $duration): int` — Prune old file store entries
- `$stash->purgeFileStores(): void` — Purge all file stores

**Store Interface (PSR-6/PSR-16):**
- `$store->get(string $key, mixed $default = null): mixed` — Get value (PSR-16)
- `$store->getItem(string $key): Item` — Get cache item (PSR-6)
- `$store->getMultiple(iterable $keys, mixed $default = null): iterable` — Get multiple values (PSR-16)
- `$store->getItems(array $keys = []): iterable` — Get multiple items (PSR-6)
- `$store->has(string $key, string ...$keys): bool` — Check if key exists (PSR-16)
- `$store->hasItem(string $key): bool` — Check if item exists (PSR-6)
- `$store->set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool` — Set value (PSR-16)
- `$store->setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool` — Set multiple values (PSR-16)
- `$store->delete(string $key, string ...$keys): bool` — Delete key(s) (PSR-16)
- `$store->deleteItem(string $key, string ...$keys): bool` — Delete item(s) (PSR-6)
- `$store->deleteMultiple(iterable $keys): bool` — Delete multiple keys (PSR-16)
- `$store->deleteItems(array $keys): bool` — Delete multiple items (PSR-6)
- `$store->clear(): bool` — Clear all items (PSR-6/PSR-16)
- `$store->save(CacheItem $item): bool` — Save item (PSR-6)
- `$store->saveDeferred(CacheItem $item): bool` — Save item deferred (PSR-6)
- `$store->commit(): bool` — Commit deferred items (PSR-6)
- `$store->clearDeferred(): bool` — Clear deferred items

**Store Extensions:**
- `$store->fetch(string $key, Closure $generator): mixed` — Fetch or generate value
- `$store->getNamespace(): string` — Get namespace
- `$store->getDriver(): Driver` — Get driver
- `$store->getDriverKeys(): array` — Get all keys in namespace
- `$store->count(): int` — Count items (Countable)
- `$store[$key]` — Array access (get/set/isset/unset)
- `$store->$key` — Object access (returns Item)

**Pile-Up Protection:**
- `$store->pileUpIgnore(): static` — Ignore pile-ups
- `$store->pileUpPreempt(?int $time = null): static` — Preempt pile-ups
- `$store->pileUpSleep(?int $time = null, ?int $attempts = null): static` — Sleep on pile-ups
- `$store->pileUpValue(): static` — Return fallback value on pile-ups
- `$store->setPileUpPolicy(PileUpPolicy $policy): static` — Set pile-up policy
- `$store->getPileUpPolicy(): PileUpPolicy` — Get pile-up policy
- `$store->setPreemptTime(int $time): static` — Set preempt time
- `$store->getPreemptTime(): int` — Get preempt time
- `$store->setSleepTime(int $time): static` — Set sleep time
- `$store->getSleepTime(): int` — Get sleep time
- `$store->setSleepAttempts(int $attempts): static` — Set sleep attempts
- `$store->getSleepAttempts(): int` — Get sleep attempts

**Item:**
- `$item->getKey(): string` — Get key
- `$item->get(): mixed` — Get value
- `$item->set(mixed $value): static` — Set value
- `$item->isHit(): bool` — Check if hit
- `$item->isMiss(): bool` — Check if miss
- `$item->expiresAt(DateTimeInterface|DateInterval|string|int|null $expiration): static` — Set expiration time
- `$item->expiresAfter(DateInterval|string|int|null $time): static` — Set expiration interval
- `$item->getExpiration(): ?Carbon` — Get expiration
- `$item->getExpirationTimestamp(): ?int` — Get expiration timestamp
- `$item->getTimeRemaining(): ?CarbonInterval` — Get time remaining
- `$item->lock(): bool` — Lock item
- `$item->unlock(): bool` — Unlock item
- `$item->save(): bool` — Save item

**DriverManager:**
- `new DriverManager(DriverConfig|NamespaceConfig|FileStoreConfig ...$settings)` — Constructor
- `$manager->getNamespaceConfig(string $namespace): NamespaceConfig` — Get namespace config
- `$manager->getDriverConfig(NamespaceConfig $namespace, ?string $defaultPrefix = null): DriverConfig` — Get driver config
- `$manager->getDriver(DriverConfig $config): Driver` — Get driver instance
- `$manager->getDriverForNamespace(NamespaceConfig $namespace, ?string $defaultPrefix = null): Driver` — Get driver for namespace
- `$manager->getFileStoreConfig(string $namespace): FileStoreConfig` — Get file store config
- `$manager->ensureDefaultDrivers(?string $defaultPrefix = null): void` — Ensure default drivers
- `$manager->registerDriver(string $name, Driver $driver): void` — Register custom driver

**FileStore:**
- `$fileStore->set(string $key, string|File $value): bool` — Set file
- `$fileStore->setMultiple(iterable $values): bool` — Set multiple files
- `$fileStore->get(string $key, DateInterval|string|Stringable|int|null $ttl = null): ?File` — Get file
- `$fileStore->fetch(string $key, Closure $generator, DateInterval|string|Stringable|int|null $ttl = null): ?File` — Fetch or generate file
- `$fileStore->has(string $key, string ...$keys): bool` — Check if file exists
- `$fileStore->delete(string $key, string ...$keys): bool` — Delete file(s)
- `$fileStore->deleteOlderThan(DateInterval|string|Stringable|int $ttl): int` — Delete older files
- `$fileStore->deleteBeginningWith(string $prefix): int` — Delete files with prefix
- `$fileStore->deleteMatches(string $pattern): int` — Delete matching files
- `$fileStore->clear(): void` — Clear all files
- `$fileStore->scan(iterable $keys, DateInterval|string|Stringable|int $ttl): iterable` — Scan files
- `$fileStore->scanOlderThan(DateInterval|string|Stringable|int $ttl): iterable` — Scan older files
- `$fileStore->scanBeginningWith(string $prefix): iterable` — Scan files with prefix
- `$fileStore->scanMatches(string $pattern): iterable` — Scan matching files
- `$fileStore->scanAll(): iterable` — Scan all files
- `$fileStore->scanKeys(): iterable` — Scan all keys
- `$fileStore->getCreationDate(string $key): ?Carbon` — Get creation date
- `$fileStore->getCreationTime(string $key): ?int` — Get creation time
- `$fileStore->count(): int` — Count files (Countable)
- `$fileStore[$key]` — Array access (get/set/isset/unset)

## Dependencies

### Decode Labs

- **`decodelabs/archetype`**: Used for resolving custom Store and FileStore implementations by namespace.
- **`decodelabs/atlas`**: Used for file and directory operations in File and FileStore drivers.
- **`decodelabs/coercion`**: Used for type coercion in cache operations (TTL conversion, key validation).
- **`decodelabs/dictum`**: Used for configuration and data structures.
- **`decodelabs/exceptional`**: Used for exception handling throughout the package.
- **`decodelabs/kingdom`**: Used for service container integration (`Service` interface).
- **`decodelabs/monarch`**: Used for service location (paths, Kingdom container).

### External

- **PHP**: See `composer.json` for supported PHP versions.
- **`psr/cache`**: PSR-6 Cache Item Pool interfaces.
- **`psr/simple-cache`**: PSR-16 Simple Cache interfaces.
- **`nesbot/carbon`**: Used for date/time handling in expiration and TTL calculations.

### Optional

- **`ext-memcached`**: Detected at runtime if installed, used for Memcache driver support.
- **`ext-apcu`**: Detected at runtime if installed, used for APCu driver support.
- **`ext-redis`**: Detected at runtime if installed, used for Redis driver support.
- **`predis/predis`**: Detected at runtime if installed, used for Predis driver support.
- **`decodelabs/dovetail`**: Detected at runtime if installed, used for Dovetail config integration.

## Behaviour & Contracts

### Invariants

- Cache keys must be non-empty strings and cannot contain reserved characters: `{}()/\@:`.
- Cache items are immutable once saved (modifications require new item).
- Deferred saves are batched and committed atomically.
- Namespace isolation ensures keys from different namespaces do not collide.
- Driver availability is checked before instantiation.
- File stores use TTL-based expiration (files older than TTL are considered expired).

### Input & Output Contracts

**Cache Key Validation:**
- Keys must be non-empty strings.
- Keys cannot contain reserved characters: `{}()/\@:`.
- Invalid keys throw `InvalidArgumentException` (PSR-6) or `InvalidArgumentException` (PSR-16).

**Cache Operations:**
- `get()` returns `null` or default value if key not found.
- `getItem()` always returns an Item (may be a miss).
- `set()` returns `true` on success, `false` on failure.
- `delete()` returns `true` if at least one key deleted, `false` otherwise.
- `clear()` returns `true` on success, `false` on failure.

**TTL Handling:**
- TTL can be `null` (no expiration), `int` (seconds), or `DateInterval`.
- Expiration times are stored as Unix timestamps.
- Expired items are treated as misses.

**Deferred Saves:**
- `saveDeferred()` adds item to deferred queue.
- `commit()` saves all deferred items atomically.
- `clearDeferred()` clears deferred queue without saving.

**Pile-Up Protection:**
- `Ignore`: No protection, multiple generators may run.
- `Preempt`: First generator runs, others return stale value after timeout.
- `Sleep`: Generators sleep and retry until lock released.
- `Value`: Generators return fallback value if lock exists.

**Driver Selection:**
- Drivers are selected based on namespace configuration.
- Fallback to default driver if namespace not configured.
- Automatic driver detection if no configuration provided (Memcache → Redis → APCu → File).

**File Store Operations:**
- Files are stored with creation timestamp.
- TTL-based expiration checks file age.
- File operations use Atlas file system abstraction.

## Error Handling

- **Invalid cache key**: Throws `InvalidArgumentException` (PSR-6/PSR-16) for invalid keys.
- **Driver unavailable**: Throws `ComponentUnavailable` exception if no drivers available.
- **Storage failure**: Storage operations return `false` on failure (no exceptions).
- **Lock timeout**: Pile-up protection handles lock timeouts according to policy.
- **File operations**: File store operations may throw exceptions from Atlas file system.

## Configuration & Extensibility

### Custom Store Implementations

Register custom Store classes via Archetype:

```php
use DecodeLabs\Archetype;
use DecodeLabs\Stash\Store;

$archetype->map(Store::class, 'MyNamespace', MyCustomStore::class);

$myCache = $stash->load('MyNamespace'); // Uses MyCustomStore
```

### Custom Drivers

Implement `Driver` interface and register via DriverManager:

```php
use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\DriverConfig;

class MyDriver implements Driver
{
    public static function isAvailable(): bool
    {
        return true;
    }

    // Implement Driver methods...
}

$manager->registerDriver('MyDriver', new MyDriver($config));
```

### Driver Configuration

Configure drivers via DriverManager:

```php
use DecodeLabs\Stash\DriverManager;
use DecodeLabs\Stash\DriverConfig\Redis as RedisConfig;
use DecodeLabs\Stash\DriverConfig\Memcache as MemcacheConfig;
use DecodeLabs\Stash\NamespaceConfig;

$manager = new DriverManager(
    new RedisConfig('localRedis'),
    new RedisConfig('anotherRedis', host: '123.456.789.000', port: 6379),
    new MemcacheConfig('remoteMemcache', host: '123.456.789.000', port: 11211),
    new NamespaceConfig('MyStore', driver: 'remoteMemcache'),
);
```

### Pile-Up Policy Configuration

Configure pile-up policies per namespace:

```php
use DecodeLabs\Stash\NamespaceConfig;
use DecodeLabs\Stash\PileUpPolicy;

$config = new NamespaceConfig(
    namespace: 'MyStore',
    pileUpPolicy: PileUpPolicy::Preempt,
    preemptTime: 30,
    sleepTime: 500,
    sleepAttempts: 10
);
```

## Interactions with Other Packages

- **Archetype**: Used for resolving custom Store and FileStore implementations by namespace.
- **Atlas**: Used for file and directory operations in File and FileStore drivers.
- **Coercion**: Used for type coercion in cache operations (TTL conversion, key validation).
- **Kingdom**: Used for service container integration. Stash implements `Service` interface.
- **Monarch**: Used for service location (paths, Kingdom container).
- **Carbon**: Used for date/time handling in expiration and TTL calculations.
- **Genesis**: Optional integration for build tasks (StashPurge task).
- **Commandment**: Optional integration for CLI commands (Cache\Purge command).

## Usage Examples

### Basic Cache Operations

```php
use DecodeLabs\Stash;

$stash = new Stash($archetype, $driverManager);
$myCache = $stash->load('MyCache');

// Check and set
if (!$myCache->has('myValue')) {
    $myCache->set('myValue', [1, 2, 3]);
}

// Get with default
$total = 0;
foreach ($myCache->get('myValue', []) as $number) {
    $total += $number;
}

// Delete
$myCache->delete('myValue');
```

### Fetch Pattern

```php
use DecodeLabs\Stash;

$myCache = $stash->load('MyCache');

// Fetch or generate
$myValue = $myCache->fetch('myValue', function() {
    return [1, 2, 3]; // Only called if key not found
});
```

### Array Access

```php
use DecodeLabs\Stash;

$myCache = $stash->load('MyCache');

// Array access
if (!isset($myCache['myValue'])) {
    $myCache['myValue'] = 'Hello world';
}

echo $myCache['myValue'];
unset($myCache['myValue']);
```

### Object Access

```php
use DecodeLabs\Stash;

$myCache = $stash->load('MyCache');

// Object access (returns Item)
$item = $myCache->myValue;

if (!$item->isHit()) {
    $item->set('Hello world');
    $item->expiresAfter(3600); // 1 hour
    $item->save();
}

echo $item->get();
$item->delete();
```

### Deferred Saves

```php
use DecodeLabs\Stash;

$myCache = $stash->load('MyCache');

// Defer multiple saves
$myCache->saveDeferred($myCache->getItem('key1')->set('value1'));
$myCache->saveDeferred($myCache->getItem('key2')->set('value2'));
$myCache->saveDeferred($myCache->getItem('key3')->set('value3'));

// Commit all at once
$myCache->commit();
```

### Pile-Up Protection

```php
use DecodeLabs\Stash;
use DecodeLabs\Stash\PileUpPolicy;

$myCache = $stash->load('MyCache');

// Configure pile-up policy
$myCache->pileUpPreempt(30); // Preempt after 30 seconds

// Fetch with pile-up protection
$value = $myCache->fetch('expensive', function() {
    // Only one generator runs, others get stale value after timeout
    return expensiveOperation();
});
```

### File Store

```php
use DecodeLabs\Stash;

$fileStore = $stash->loadFileStore('MyFiles');

// Store file
$fileStore->set('myFile', $fileContent);

// Get file with TTL
$file = $fileStore->get('myFile', '1 hour');

// Fetch or generate file
$file = $fileStore->fetch('myFile', function() {
    return generateFile();
}, '1 day');

// Scan and delete old files
foreach ($fileStore->scanOlderThan('7 days') as $key => $file) {
    $fileStore->delete($key);
}
```

### Driver Configuration

```php
use DecodeLabs\Stash\DriverManager;
use DecodeLabs\Stash\DriverConfig\Redis as RedisConfig;
use DecodeLabs\Stash\DriverConfig\Memcache as MemcacheConfig;
use DecodeLabs\Stash\NamespaceConfig;
use DecodeLabs\Stash\FileStoreConfig;

$manager = new DriverManager(
    new RedisConfig('localRedis'),
    new RedisConfig('anotherRedis', host: '123.456.789.000', port: 6379),
    new MemcacheConfig('remoteMemcache', host: '123.456.789.000', port: 11211),
    new NamespaceConfig('MyStore', driver: 'remoteMemcache'),
    new FileStoreConfig('MyFiles', path: '/tmp/stash/fileStore'),
);
```

### Kingdom Integration

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

## Implementation Notes (for Contributors)

### Driver Architecture

- Drivers implement `Driver` interface with `isAvailable()` static method.
- Drivers handle namespace and key generation internally.
- Drivers support locking for pile-up protection.
- Drivers must handle expiration timestamps (Unix timestamps).

### Key Generation

- Keys are prefixed with namespace and optional prefix.
- Key generation uses traits (`KeyGenTrait`, `IndexedKeyGenTrait`).
- Keys are validated before storage (no reserved characters).

### Pile-Up Protection

- Locking uses driver's `storeLock()`, `fetchLock()`, `deleteLock()` methods.
- Lock TTL defaults to 30 seconds (`Item::LockTTL`).
- Policies: Ignore (no protection), Preempt (return stale after timeout), Sleep (retry), Value (return fallback).

### Deferred Saves

- Deferred items stored in `$deferred` array.
- `commit()` saves all deferred items and clears array.
- `clearDeferred()` clears without saving.

### File Store

- Files stored with creation timestamp.
- TTL-based expiration checks file age.
- File operations use Atlas file system abstraction.
- Supports scanning by prefix, pattern, age, etc.

### Driver Selection

- `DriverManager` selects driver based on namespace configuration.
- Falls back to default driver if namespace not configured.
- Automatic detection tries Memcache → Redis → APCu → File.
- Fallback driver always available (File or PhpArray).

### Expiration Handling

- Expiration stored as Unix timestamp.
- `isHit()` checks expiration before returning hit status.
- Expired items treated as misses.
- TTL conversion uses Carbon for date interval handling.

## Testing & Quality

**Current Status:**
- Code quality: 4.5/5
- README quality: 3/5
- Documentation: 0/5 (no formal docs yet)
- Tests: 0/5 (no test suite yet)

**Testing Considerations:**
- Cache operations should be tested for:
  - Get/set/delete operations
  - TTL expiration
  - Namespace isolation
  - Key validation
  - Deferred saves
  - Pile-up protection

- Drivers should be tested for:
  - Storage and retrieval
  - Expiration handling
  - Locking mechanisms
  - Namespace isolation
  - Key generation
  - Purge operations

- File stores should be tested for:
  - File storage and retrieval
  - TTL-based expiration
  - Scanning operations
  - Deletion operations
  - Permission handling

- Edge cases should be tested for:
  - Concurrent access
  - Driver failures
  - Expired items
  - Invalid keys
  - Large values
  - Empty namespaces

## Roadmap & Future Ideas

- **Cache warming**: Tools for preloading cache entries
- **Cache invalidation**: Tag-based invalidation strategies
- **Cache analytics**: Monitoring and analytics tools
- **Distributed coordination**: Distributed cache coordination mechanisms
- **Cache versioning**: Support for cache versioning and migration
- **Performance optimization**: Caching of driver instances and connection pooling
- **Additional drivers**: Support for more storage backends (MongoDB, Couchbase, etc.)
- **Cache compression**: Automatic compression of large values

## References

- Package repository: https://github.com/decodelabs/stash
- Composer package: https://packagist.org/packages/decodelabs/stash
- PSR-6: https://www.php-fig.org/psr/psr-6/ (Cache Item Pool Interface)
- PSR-16: https://www.php-fig.org/psr/psr-16/ (Simple Cache Interface)
- Related packages:
  - `decodelabs/archetype` — Class resolution
  - `decodelabs/atlas` — File system operations
  - `decodelabs/coercion` — Type coercion
  - `decodelabs/kingdom` — Service container
  - `decodelabs/monarch` — Service location

