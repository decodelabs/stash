<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Dovetail\Config;

use DecodeLabs\Dovetail\Config;
use DecodeLabs\Dovetail\ConfigTrait;
use DecodeLabs\Stash\Config as ConfigInterface;
use DecodeLabs\Stash\PileUpPolicy;

class Stash implements Config, ConfigInterface
{
    use ConfigTrait;


    public static function getDefaultValues(): array
    {
        return [
            'stores' => [
            ],
            'drivers' => [
            ],
            'fileStores' => [
                'default' => [
                    'path' => null
                ]
            ]
        ];
    }


    public function getDriverFor(
        string $namespace
    ): ?string {
        return
            $this->data->stores->__get($namespace)->driver->as('?string') ??
            $this->data->stores->default->driver->as('?string');
    }

    public function isDriverEnabled(
        string $driver
    ): bool {
        return $this->data->drivers->__get($driver)->enabled->as('bool', [
            'default' => true
        ]);
    }


    public function getAllDrivers(): array
    {
        /** @var array<string> $output */
        $output = $this->data->drivers->getKeys();

        return $output;
    }

    /**
     * @return array<string,mixed>
     */
    public function getDriverSettings(
        string $driver
    ): ?array {
        /** @var array<string,mixed> */
        $output = $this->data->drivers->__get($driver)->toArray();
        return $output;
    }

    public function getPileUpPolicy(
        string $namespace
    ): ?PileUpPolicy {
        return PileUpPolicy::tryFrom(
            $this->data->stores->__get($namespace)->pileUpPolicy->as('?string') ??
            $this->data->stores->default->pileUpPolicy->as('?string') ?? ''
        );
    }

    /**
     * @return ?positive-int
     */
    public function getPreemptTime(
        string $namespace
    ): ?int {
        /** @var ?positive-int */
        $output =
            $this->data->stores->__get($namespace)->preemptTime->as('?int') ??
            $this->data->stores->default->preemptTime->as('?int');

        return $output;
    }

    /**
     * @return ?positive-int
     */
    public function getSleepTime(
        string $namespace
    ): ?int {
        /** @var ?positive-int */
        $output =
            $this->data->stores->__get($namespace)->sleepTime->as('?int') ??
            $this->data->stores->default->sleepTime->as('?int');

        return $output;
    }

    /**
     * @return ?positive-int
     */
    public function getSleepAttempts(
        string $namespace
    ): ?int {
        /** @var ?positive-int */
        $output =
            $this->data->stores->__get($namespace)->sleepAttempts->as('?int') ??
            $this->data->stores->default->sleepAttempts->as('?int');

        return $output;
    }


    public function getFileStoreSettings(
        string $namespace
    ): array {
        /** @var array<string,mixed> */
        $output = array_merge(
            $this->data->fileStores->default->toArray(),
            $this->data->fileStores->__get($namespace)->toArray()
        );

        return $output;
    }

    public function getAllFileStoreSettings(): array
    {
        $output = [];

        foreach ($this->data->fileStores->getKeys() as $key) {
            $key = (string)$key;
            $output[$key] = $this->getFileStoreSettings($key);
        }

        return $output;
    }
}
