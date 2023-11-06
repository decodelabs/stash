<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

enum PileUpPolicy: string
{
    case IGNORE = 'ignore';
    case PREEMPT = 'preempt';
    case SLEEP = 'sleep';
    case VALUE = 'value';
}
