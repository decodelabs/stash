<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Atlas\Dir\Local as Dir;
use DecodeLabs\Atlas\File as FileInterface;
use DecodeLabs\Monarch;
use DecodeLabs\Stash\Driver;
use DecodeLabs\Stash\DriverConfig\Fallback as FallbackConfig;
use DecodeLabs\Stash\DriverConfig\File as FileConfig;
use ReflectionClass;
use Throwable;

class File implements Driver
{
    use KeyGenTrait;

    /** @var non-empty-string KeySeparator */
    protected const string KeySeparator = '/';
    protected const string Extension = '.cache';

    protected Dir $dir;
    public int $dirPermissions = 0770;
    public int $filePermissions = 0660;

    public static function isAvailable(): bool
    {
        return true;
    }


    public function __construct(
        FallbackConfig|FileConfig $config
    ) {
        $this->generatePrefix($config->prefix);

        // Path
        if (
            $config instanceof FileConfig &&
            $config->path !== null
        ) {
            $path = $config->path;
        } else {
            $name = lcfirst((new ReflectionClass($this))->getShortName());
            $path = Monarch::getPaths()->localData . '/stash/cache@' . $name;
        }

        $this->dir = new Dir($path);

        // Permissions
        if ($config instanceof FileConfig) {
            if ($config->dirPermissions !== null) {
                $this->dirPermissions = $config->dirPermissions;
            }

            if ($config->filePermissions !== null) {
                $this->filePermissions = $config->filePermissions;
            }
        }
    }

    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool {
        $this->dir->ensureExists($this->dirPermissions);
        $file = $this->getFile($namespace, $key);
        $data = $this->buildFileContent($file, $namespace, $key, $value, $created, $expires);

        try {
            $file->putContents($data);
            $output = true;
        } catch (Throwable $e) {
            $output = false;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file->path, true);
        }

        return $output;
    }

    protected function buildFileContent(
        FileInterface $file,
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): string {
        return serialize([
            'namespace' => $namespace,
            'key' => $key,
            'expires' => $expires,
            'value' => $value
        ]);
    }

    public function fetch(
        string $namespace,
        string $key
    ): ?array {
        $file = $this->getFile($namespace, $key);
        $file->clearStatCache();

        if (!$file->exists()) {
            return null;
        }

        if (null === ($data = $this->loadFileContent($file))) {
            return null;
        }

        if (
            $data['namespace'] !== $namespace ||
            $data['key'] !== $key
        ) {
            return null;
        }

        return [
            $data['value'],
            $data['expires'] ?? null
        ];
    }

    /**
     * @return array{'namespace': string, 'key': string, 'expires': ?int, 'value': mixed}|null
     */
    protected function loadFileContent(
        FileInterface $file
    ): ?array {
        try {
            $data = unserialize($file->getContents());
        } catch (Throwable $e) {
            return null;
        }

        if (
            is_null($data) ||
            !is_array($data)
        ) {
            return null;
        }

        /** @var array{'namespace': string, 'key': string, 'expires': ?int, 'value': mixed} */
        return $data;
    }

    public function delete(
        string $namespace,
        string $key
    ): bool {
        $key = $this->inspectKey($namespace, $key);
        $root = $this->hashKey($key['key']);

        if ($key['children']) {
            $this->dir->deleteDir($root);
        }

        if ($key['self']) {
            $this->dir->deleteFile($root . static::Extension);
        }

        return true;
    }

    public function clearAll(
        string $namespace
    ): bool {
        $key = $this->inspectKey($namespace, null);
        $root = $this->hashKey($key['key']);
        $this->dir->deleteDir($root);
        return true;
    }


    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $file = $this->getLockFile($namespace, $key);

        try {
            $file->putContents($expires);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function fetchLock(
        string $namespace,
        string $key
    ): ?int {
        $file = $this->getLockFile($namespace, $key);
        $file->clearStatCache();

        if (!$file->exists()) {
            return null;
        }

        $expires = $file->getContents();

        if (empty($expires)) {
            return null;
        }

        return (int)$expires;
    }

    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $file = $this->getLockFile($namespace, $key);
        $file->delete();
        return true;
    }


    protected function getFile(
        string $namespace,
        string $key
    ): FileInterface {
        $key = $this->createKey($namespace, $key);
        $key = $this->hashKey($key) . static::Extension;
        return $this->dir->getFile($key);
    }

    protected function getLockFile(
        string $namespace,
        string $key
    ): FileInterface {
        $key = $this->createKey($namespace, $key);
        $key = $this->hashKey($key) . '.lock';
        return $this->dir->getFile($key);
    }

    protected function hashKey(
        string $key
    ): string {
        $key = trim($key, '/');
        $parts = explode(static::KeySeparator, $key);

        foreach ((array)$parts as &$part) {
            if ($part !== '') {
                $part = md5((string)$part);
            }
        }

        return implode(static::KeySeparator, $parts);
    }

    public function count(
        string $namespace
    ): int {
        /** @var int<0,max> */
        $output = $this->dir->getDir($this->prefix . '/' . $namespace)->countFiles();
        return $output;
    }

    public function getKeys(
        string $namespace
    ): array {
        $output = [];

        foreach ($this->dir->getDir($this->prefix . '/' . $namespace)->scanFiles() as $name => $file) {
            $output[] = $name;
        }

        return $output;
    }

    public function purge(): void
    {
        $this->dir->delete();
    }

    protected function getKeySeparator(): string
    {
        return static::KeySeparator;
    }
}
