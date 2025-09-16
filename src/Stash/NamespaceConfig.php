<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

class NamespaceConfig
{
    public function __construct(
        public string $namespace,
        public ?string $driver = null,
        public ?PileUpPolicy $pileUpPolicy = null,
        /** @var ?positive-int */
        public ?int $preemptTime = null,
        /** @var ?positive-int */
        public ?int $sleepTime = null,
        /** @var ?positive-int */
        public ?int $sleepAttempts = null,
    ) {
    }
}
