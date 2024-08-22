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
use DecodeLabs\Genesis;
use DecodeLabs\Stash\FileStore;
use Stringable;
use Throwable;

class Generic implements FileStore
{
    protected const Extension = '.cache';

    protected string $prefix = 'c-';
    protected string $namespace;

    protected Dir $dir;
    protected int $dirPerms = 0770;
    protected int $filePerms = 0660;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        string $namespace,
        array $settings = []
    ) {
        $this->namespace = $namespace;
        $this->prefix = Coercion::toString($settings['prefix'] ?? $this->prefix);


        // Path
        if (null === ($path = Coercion::toStringOrNull($settings['path'] ?? null))) {
            if (class_exists(Genesis::class)) {
                $basePath = Genesis::$hub->getLocalDataPath();
            } else {
                $basePath = getcwd();
            }

            $path = $basePath . '/stash/fileStore/' . $this->normalizeKey($namespace);
        }

        $this->dir = new Dir($path);

        // Permissions
        if (isset($settings['dirPermissions'])) {
            $this->setDirPermissions(Coercion::toInt($settings['dirPermissions']));
        }

        if (isset($settings['filePermissions'])) {
            $this->setFilePermissions(Coercion::toInt($settings['filePermissions']));
        }
    }



    /**
     * Set default dir perms
     *
     * @return $this
     */
    public function setDirPermissions(
        int $perms
    ): static {
        $this->dirPerms = $perms;
        return $this;
    }

    /**
     * Get default dir perms
     */
    public function getDirPermissions(): int
    {
        return $this->dirPerms;
    }

    /**
     * Set default file perms
     *
     * @return $this
     */
    public function setFilePermissions(
        int $perms
    ): static {
        $this->filePerms = $perms;
        return $this;
    }

    /**
     * Get default file perms
     */
    public function getFilePermissions(): int
    {
        return $this->filePerms;
    }




    /**
     * Set file
     *
     * @param string|File $file
     */
    public function set(
        string $key,
        string|File $file
    ): bool {
        $this->dir->ensureExists($this->dirPerms);
        $file = $this->normalizeFile($file);
        $target = $this->getFile($key);

        $target->putContents($file);
        return true;
    }

    /**
     * Set multiple files
     *
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

    /**
     * Get file
     */
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
     * Get multiple files
     *
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
        $ttl = Coercion::toDateInterval($ttl);

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
            Coercion::toInt($file->getLastModified())
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



    /**
     * Delete file
     */
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
        $ttl = Coercion::toDateInterval($ttl);

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
        return $this->dir->countFiles();
    }



    /**
     * Shortcut set()
     *
     * @param string|File $file
     */
    public function offsetSet(
        mixed $key,
        mixed $file
    ): void {
        $this->set((string)$key, $file);
    }

    /**
     * Shortcut get()
     *
     * @return ?File
     */
    public function offsetGet(
        mixed $key
    ): mixed {
        return $this->get($key);
    }

    /**
     * Shortcut has()
     */
    public function offsetExists(
        mixed $key
    ): bool {
        return $this->has($key);
    }

    /**
     * Shortcut delete()
     *
     * @param string $key
     */
    public function offsetUnset(
        mixed $key
    ): void {
        $this->delete($key);
    }



    /**
     * Normalize key
     */
    protected function normalizeKey(
        string $key
    ): string {
        return Dictum::text($key)
            ->toAscii()
            ->replace(' ', '-')
            ->regexReplace('[\/\\\?%*:|"<>]', '_')
            ->__toString();
    }

    /**
     * Create key
     */
    protected function createKey(
        string $key
    ): string {
        return $this->prefix . $this->normalizeKey($key) . self::Extension;
    }

    /**
     * Normalize input file
     *
     * @phpstan-return ($file is null ? File : null)
     */
    protected function normalizeFile(
        string|File|null $file
    ): ?File {
        if ($file === null) {
            return null;
        }

        if (!$file instanceof File) {
            $file = Atlas::newMemoryFile()->putContents(Coercion::toString($file));
        }

        return $file;
    }

    /**
     * Get target file
     */
    protected function getFile(
        string $key
    ): File {
        $key = $this->createKey($key);
        return $this->dir->getFile($key);
    }
}
