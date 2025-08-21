<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Genesis\Build\Task;

use DecodeLabs\Stash;
use DecodeLabs\Terminus\Session;

class StashPurge implements PostActivation
{
    public int $priority {
        get => 100;
    }

    public string $description {
        get => 'Purging caches';
    }

    public function __construct(
        protected Stash $stash
    ) {
    }

    public function run(
        Session $session
    ): void {
        if (function_exists('opcache_reset')) {
            $session->{'.green'}('Opcache');
            opcache_reset();
        }

        $session->{'.green'}('Stash');

        $this->stash->purge();
    }
}
