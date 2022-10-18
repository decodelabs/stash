<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

/**
 * global helpers
 */

namespace DecodeLabs\Stash
{
    use DecodeLabs\Stash;
    use DecodeLabs\Veneer;

    // Veneer
    Veneer::register(Context::class, Stash::class);
}
