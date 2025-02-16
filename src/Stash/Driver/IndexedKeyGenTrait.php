<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

trait IndexedKeyGenTrait
{
    use KeyGenTrait;

    /**
     * @var array<string,int>
     */
    private array $keyCache = [];

    private float $keyCacheTime = 0;
    private float $keyCacheTimeLimit = 1;

    /**
     * Create path key
     *
     * @return array{0: string, 1: string}
     */
    protected function createNestedKey(
        string $namespace,
        ?string $key
    ): array {
        $separator = $this->getKeySeparator();
        $keyString = '';
        $pathPrefix = $this->prefix . ':p' . $separator;

        $time = microtime(true);

        if (($time - $this->keyCacheTime) >= $this->keyCacheTimeLimit) {
            $this->keyCacheTime = $time;
            $this->keyCache = [];
        }

        $parts = $key === null ? [] : explode('.', trim($key, '.'));
        array_unshift($parts, $namespace);
        $count = count($parts);

        foreach ($parts as $i => $part) {
            $keyString .= $part;
            $pathKey = $pathPrefix . md5($keyString);

            if ($i < $count - 1) {
                if (isset($this->keyCache[$pathKey])) {
                    $index = $this->keyCache[$pathKey];
                } else {
                    $this->keyCache[$pathKey] = $index = $this->getPathIndex($pathKey);
                }

                $keyString .= '_' . $index . $separator;
            }
        }

        return [
            $this->prefix . ':c' . $separator . md5($keyString),
            $pathKey
        ];
    }

    abstract protected function getPathIndex(
        string $pathKey
    ): int;
}
