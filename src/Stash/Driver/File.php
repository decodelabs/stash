<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Atlas\Dir\Local as Dir;
use DecodeLabs\Atlas\File as FileInterface;
use DecodeLabs\Coercion;
use DecodeLabs\Genesis;
use DecodeLabs\Stash\Driver;
use ReflectionClass;
use Throwable;

class File implements Driver
{
    use KeyGenTrait;

    protected const KeySeparator = '/';
    protected const Extension = '.cache';

    protected Dir $dir;
    protected int $dirPerms = 0770;
    protected int $filePerms = 0660;

    /**
     * Can this be loaded?
     */
    public static function isAvailable(): bool
    {
        return true;
    }


    /**
     * Init with settings
     */
    public function __construct(
        array $settings
    ) {
        $this->generatePrefix(
            Coercion::toStringOrNull($settings['prefix'] ?? null)
        );


        // Path
        if (null === ($path = Coercion::toStringOrNull($settings['path'] ?? null))) {
            if (class_exists(Genesis::class)) {
                $basePath = Genesis::$hub->getLocalDataPath();
            } else {
                $basePath = getcwd();
            }

            $name = lcfirst((new ReflectionClass($this))->getShortName());
            $path = $basePath . '/stash/cache@' . $name;
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
     * Store item data
     */
    public function store(
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): bool {
        $this->dir->ensureExists($this->dirPerms);
        $file = $this->getFile($namespace, $key);
        $data = $this->buildFileContent($file, $namespace, $key, $value, $created, $expires);

        try {
            $file->putContents($data);
            $output = true;
        } catch (\Throwable $e) {
            $output = false;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file->getPath(), true);
        }

        return $output;
    }

    /**
     * Store item data in file
     */
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

    /**
     * Fetch item data
     */
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
     * Get item data from file
     *
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

    /**
     * Remove item from store
     */
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

    /**
     * Clear all values from store
     */
    public function clearAll(
        string $namespace
    ): bool {
        $key = $this->inspectKey($namespace, null);
        $root = $this->hashKey($key['key']);
        $this->dir->deleteDir($root);
        return true;
    }



    /**
     * Save a lock for a key
     */
    public function storeLock(
        string $namespace,
        string $key,
        int $expires
    ): bool {
        $file = $this->getLockFile($namespace, $key);

        try {
            $file->putContents($expires);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get a lock expiry for a key
     */
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

        if ($expires !== null) {
            $expires = (int)$expires;
        }

        return $expires;
    }

    /**
     * Remove a lock
     */
    public function deleteLock(
        string $namespace,
        string $key
    ): bool {
        $file = $this->getLockFile($namespace, $key);
        $file->delete();
        return true;
    }



    /**
     * Create file path from key
     */
    protected function getFile(
        string $namespace,
        string $key
    ): FileInterface {
        $key = $this->createKey($namespace, $key);
        $key = $this->hashKey($key) . static::Extension;
        return $this->dir->getFile($key);
    }

    /**
     * Create file path from key
     */
    protected function getLockFile(
        string $namespace,
        string $key
    ): FileInterface {
        $key = $this->createKey($namespace, $key);
        $key = $this->hashKey($key) . '.lock';
        return $this->dir->getFile($key);
    }

    /**
     * Hash key parts
     */
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


    /**
     * Count items
     */
    public function count(
        string $namespace
    ): int {
        return $this->dir->getDir($this->prefix . '/' . $namespace)->countFiles();
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



    /**
     * Delete EVERYTHING in this store
     */
    public function purge(): void
    {
        $this->dir->delete();
    }


    /**
     * Get key separator
     */
    protected function getKeySeparator(): string
    {
        return static::KeySeparator;
    }
}
