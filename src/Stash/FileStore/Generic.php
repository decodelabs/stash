<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\FileStore;

use Carbon\Carbon;
use Closure;
use DateInterval;
use DecodeLabs\Atlas;
use DecodeLabs\Atlas\Dir\Local as Dir;
use DecodeLabs\Atlas\File;
use DecodeLabs\Coercion;
use DecodeLabs\Dictum;
use DecodeLabs\Monarch;
use DecodeLabs\Stash\FileStore;
use DecodeLabs\Stash\FileStoreConfig;
use Stringable;
use Throwable;

class Generic implements FileStore
{
    protected const Extension = '.cache';

    protected string $prefix = 'c-';
    protected string $namespace;

    protected Dir $dir;
    public int $dirPermissions = 0770;
    public int $filePermissions = 0660;

    public function __construct(
        FileStoreConfig $config
    ) {
        $this->namespace = $config->namespace;
        $this->prefix = $config->prefix ?? $this->prefix;

        // Path
        if (null === ($path = $config->path)) {
            $path = Monarch::getPaths()->localData . '/stash/fileStore/' . $this->normalizeKey($this->namespace);
        }

        $this->dir = new Dir($path);

        // Permissions
        if ($config->dirPermissions !== null) {
            $this->dirPermissions = $config->dirPermissions;
        }

        if ($config->filePermissions !== null) {
            $this->filePermissions = $config->filePermissions;
        }
    }

    /**
     * @param string|File $file
     */
    public function set(
        string $key,
        string|File $file
    ): bool {
        $this->dir->ensureExists($this->dirPermissions);
        $file = $this->normalizeFile($file);
        $target = $this->getFile($key);

        $target->putContents($file);
        return true;
    }

    /**
     * @param iterable<string, string|File> $values
     */
    public function setMultiple(
        iterable $values
    ): bool {
        foreach ($values as $key => $file) {
            if (!$this->set($key, $file)) {
                return false;
            }
        }

        return true;
    }

    public function get(
        string $key,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File {
        $file = $this->getFile($key);
        $file->clearStatCache();

        if (
            !$file->exists() ||
            (
                $ttl !== null &&
                !$file->hasChangedIn($ttl)
            )
        ) {
            return null;
        }

        return $file;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, ?File>
     */
    public function scan(
        iterable $keys,
        DateInterval|string|Stringable|int|null $ttl = null
    ): iterable {
        $output = [];

        foreach ($keys as $key) {
            $output[$key] = $this->get($key, $ttl);
        }

        return $output;
    }


    public function scanOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): iterable {
        $output = [];
        $ttl = Coercion::asDateInterval($ttl);

        foreach ($this->dir->scanFiles() as $name => $file) {
            if (!$file->hasChangedIn($ttl)) {
                $name = substr($name, strlen($this->prefix), -strlen(self::Extension));
                $output[$name] = $file;
            }
        }

        return $output;
    }

    public function scanBeginningWith(
        string $prefix
    ): iterable {
        $prefix = $this->normalizeKey($prefix);
        $length = strlen($prefix);
        $output = [];

        foreach ($this->dir->scanFiles() as $name => $file) {
            $name = substr($name, strlen($this->prefix), -strlen(self::Extension));

            if (substr($name, 0, $length) === $prefix) {
                $output[$name] = $file;
            }
        }

        return $output;
    }

    public function scanMatches(
        string $pattern
    ): iterable {
        $output = [];

        foreach ($this->dir->scanFiles() as $name => $file) {
            $name = substr($name, strlen($this->prefix), -strlen(self::Extension));

            if (preg_match($pattern, $name)) {
                $output[$name] = $file;
            }
        }

        return $output;
    }

    public function scanAll(): iterable
    {
        foreach ($this->dir->scanFiles() as $name => $file) {
            $name = substr($name, strlen($this->prefix), -strlen(self::Extension));
            yield $name => $file;
        }
    }

    public function scanKeys(): iterable
    {
        foreach ($this->dir->scanFiles() as $name => $file) {
            yield substr($name, strlen($this->prefix), -strlen(self::Extension));
        }
    }

    public function getCreationDate(
        string $key
    ): ?Carbon {
        $file = $this->getFile($key);

        if (!$file->exists()) {
            return null;
        }

        return Carbon::createFromTimestamp(
            Coercion::asInt($file->getLastModified())
        );
    }

    public function getCreationTime(
        string $key
    ): ?int {
        if (null === ($date = $this->getCreationDate($key))) {
            return null;
        }

        return $date->getTimestamp();
    }

    public function fetch(
        string $key,
        Closure $generator,
        DateInterval|string|Stringable|int|null $ttl = null
    ): ?File {
        $file = $this->get($key, $ttl);

        if ($file === null) {
            try {
                $this->set($key, $generator($this));
            } catch (Throwable $e) {
                return null;
            }
        }

        return $this->get($key);
    }

    public function has(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        foreach ($keys as $key) {
            $file = $this->getFile($key);

            if (!$file->exists()) {
                continue;
            }

            return true;
        }

        return false;
    }



    public function delete(
        string $key,
        string ...$keys
    ): bool {
        /** @var array<string> */
        $keys = func_get_args();

        foreach ($keys as $key) {
            $this->getFile($key)->delete();
        }

        return true;
    }


    public function deleteOlderThan(
        DateInterval|string|Stringable|int $ttl
    ): int {
        $output = 0;
        $ttl = Coercion::asDateInterval($ttl);

        foreach ($this->dir->scanFiles() as $file) {
            if (!$file->hasChangedIn($ttl)) {
                $output++;
                $file->delete();
            }
        }

        return $output;
    }

    public function deleteBeginningWith(
        string $prefix
    ): int {
        $prefix = $this->prefix . $this->normalizeKey($prefix);
        $length = strlen($prefix);
        $output = 0;

        foreach ($this->dir->scanFiles() as $name => $file) {
            if (substr($name, 0, $length) === $prefix) {
                $output++;
                $file->delete();
            }
        }

        return $output;
    }

    public function deleteMatches(
        string $pattern
    ): int {
        $output = 0;

        foreach ($this->dir->scanFiles() as $name => $file) {
            $name = substr($name, strlen($this->prefix), -strlen(self::Extension));

            if (preg_match($pattern, $name)) {
                $output++;
                $file->delete();
            }
        }

        return $output;
    }

    public function clear(): void
    {
        $this->dir->emptyOut();
    }



    public function count(): int
    {
        /** @var int<0,max> */
        $output = $this->dir->countFiles();
        return $output;
    }



    /**
     * @param string|File $file
     */
    public function offsetSet(
        mixed $key,
        mixed $file
    ): void {
        $this->set((string)$key, $file);
    }

    /**
     * @return ?File
     */
    public function offsetGet(
        mixed $key
    ): mixed {
        return $this->get($key);
    }


    public function offsetExists(
        mixed $key
    ): bool {
        return $this->has($key);
    }

    /**
     * @param string $key
     */
    public function offsetUnset(
        mixed $key
    ): void {
        $this->delete($key);
    }



    protected function normalizeKey(
        string $key
    ): string {
        return Dictum::text($key)
            ->toAscii()
            ->replace(' ', '-')
            ->regexReplace('[\/\\\?%*:|"<>]', '_')
            ->__toString();
    }


    protected function createKey(
        string $key
    ): string {
        return $this->prefix . $this->normalizeKey($key) . self::Extension;
    }

    /**
     * @return ($file is null ? File : null)
     */
    protected function normalizeFile(
        string|File|null $file
    ): ?File {
        if ($file === null) {
            return null;
        }

        if (!$file instanceof File) {
            $file = Atlas::newMemoryFile()->putContents(Coercion::asString($file));
        }

        return $file;
    }

    protected function getFile(
        string $key
    ): File {
        $key = $this->createKey($key);
        return $this->dir->getFile($key);
    }
}
