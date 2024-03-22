<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

use ArrayAccess;
use Carbon\Carbon;
use Closure;
use Countable;
use DateInterval;
use DecodeLabs\Atlas\File;
use Stringable;

/**
 * @extends ArrayAccess<string, ?File>
 */
interface FileStore extends
    ArrayAccess,
    Countable
{
    /**
     * Set default dir perms
     *
     * @return $this
     */
    public function setDirPermissions(
        int $perms
    ): static;

    /**
     * Get default dir perms
     */
    public function getDirPermissions(): int;

    /**
     * Set default file perms
     *
     * @return $this
     */
    public function setFilePermissions(
        int $perms
    ): static;

    /**
     * Get default file perms
     */
    public function getFilePermissions(): int;



    /**
     * Persists data in the cache
     */
    public function set(
        string $key,
        string|File $value
    ): bool;

    /**
     * Persists a set of key => value pairs in the cache
     *
     * @param iterable<string, string|File> $values
     */
    public function setMultiple(
        iterable $values
    ): bool;

    /**
     * Fetches a value from the cache.
     */
    public function get(
        string $key,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys
     * @return iterable<string, File>
     */
    public function scan(
        iterable $keys,
        DateInterval|string|Stringable|int $ttl
    ): iterable;

    /**
     * Get files older than a certain duration
     *
     * @return iterable<string, File>
     */
    public function scanOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): iterable;

    /**
     * Get files beginning with a certain prefix
     *
     * @return iterable<string, File>
     */
    public function scanBeginningWith(
        string $prefix
    ): iterable;

    /**
    * Get files matches a regex pattern
    *
    * @return iterable<string, File>
    */
    public function scanMatches(
        string $pattern
    ): iterable;

    /**
     * Get list of all files
     *
     * @return iterable<string, File>
     */
    public function scanAll(): iterable;

    /**
     * Get list of file name keys
     *
     * @return iterable<string>
     */
    public function scanKeys(): iterable;

    /**
     * Get date the file was stored
     */
    public function getCreationDate(
        string $key
    ): ?Carbon;

    /**
     * Get time from creation date
     */
    public function getCreationTime(
        string $key
    ): ?int;

    /**
     * Get item, if miss, set $key as result of $generator
     *
     * @param Closure(FileStore): (string|File) $generator
     */
    public function fetch(
        string $key,
        Closure $generator,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File;

    /**
     * Determines whether an item is present in the cache.
     */
    public function has(
        string $key,
        string ...$keys
    ): bool;



    /**
     * Delete an item from the cache by its unique key.
     */
    public function delete(
        string $key,
        string ...$keys
    ): bool;

    /**
    * Delete files older than a certain duration
    */
    public function deleteOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): int;

    /**
     * Delete files beginning with a certain prefix
     */
    public function deleteBeginningWith(
        string $prefix
    ): int;

    /**
     * Delete files matches a regex pattern
     */
    public function deleteMatches(
        string $pattern
    ): int;

    /**
     * Wipes clean the entire cache's keys.
     */
    public function clear(): void;
}
