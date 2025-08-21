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
    public int $dirPermissions { get; set; }
    public int $filePermissions { get; set; }



    public function set(
        string $key,
        string|File $value
    ): bool;

    /**
     * @param iterable<string, string|File> $values
     */
    public function setMultiple(
        iterable $values
    ): bool;

    public function get(
        string $key,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File;

    /**
     * @param iterable<string> $keys
     * @return iterable<string, File>
     */
    public function scan(
        iterable $keys,
        DateInterval|string|Stringable|int $ttl
    ): iterable;

    /**
     * @return iterable<string, File>
     */
    public function scanOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): iterable;

    /**
     * @return iterable<string, File>
     */
    public function scanBeginningWith(
        string $prefix
    ): iterable;

    /**
    * @return iterable<string, File>
    */
    public function scanMatches(
        string $pattern
    ): iterable;

    /**
     * @return iterable<string, File>
     */
    public function scanAll(): iterable;

    /**
     * @return iterable<string>
     */
    public function scanKeys(): iterable;

    public function getCreationDate(
        string $key
    ): ?Carbon;

    public function getCreationTime(
        string $key
    ): ?int;

    /**
     * @param Closure(FileStore): (string|File) $generator
     */
    public function fetch(
        string $key,
        Closure $generator,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File;

    public function has(
        string $key,
        string ...$keys
    ): bool;



    public function delete(
        string $key,
        string ...$keys
    ): bool;

    public function deleteOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): int;

    public function deleteBeginningWith(
        string $prefix
    ): int;

    public function deleteMatches(
        string $pattern
    ): int;

    public function clear(): void;
}
