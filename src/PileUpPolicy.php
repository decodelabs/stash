<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash;

interface PileUpPolicy
{
    public const IGNORE = 'ignore';
    public const PREEMPT = 'preempt';
    public const SLEEP = 'sleep';
    public const VALUE = 'value';

    public const KEYS = [
        'ignore', 'preempt', 'sleep', 'value'
    ];
}
