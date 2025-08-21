<?php

/**
 * @package Fabric
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Commandment\Action\Cache;

use DecodeLabs\Commandment\Action;
use DecodeLabs\Commandment\Request;
use DecodeLabs\Stash;
use DecodeLabs\Terminus\Session;

class Purge implements Action
{
    public function __construct(
        protected Session $io,
        protected Stash $stash
    ) {
    }

    public function execute(
        Request $request,
    ): bool {
        if (function_exists('opcache_reset')) {
            $this->io->{'.green'}('Opcache');
            opcache_reset();
        }

        $this->io->{'.green'}('Stash');
        $this->stash->purge();

        return true;
    }
}
