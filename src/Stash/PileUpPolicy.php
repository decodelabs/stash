<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

enum PileUpPolicy: string
{
    case Ignore = 'ignore';
    case Preempt = 'preempt';
    case Sleep = 'sleep';
    case Value = 'value';
}
