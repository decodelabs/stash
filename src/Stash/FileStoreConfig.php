<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

class FileStoreConfig
{
    public function __construct(
        public string $namespace,
        public ?string $prefix = null,
        public ?string $path = null,
        public ?int $dirPermissions = null,
        public ?int $filePermissions = null,
    ) {
    }
}
