<?php

/**
 * @package Stash
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Stash\Driver;

use DecodeLabs\Atlas\File as FileInterface;
use Throwable;

class PhpFile extends File
{
    protected const string Extension = '.php';

    protected function buildFileContent(
        FileInterface $file,
        string $namespace,
        string $key,
        mixed $value,
        int $created,
        ?int $expires
    ): string {
        $output = '<?php' . PHP_EOL . 'return ';

        $output .= var_export([
            'namespace' => $namespace,
            'key' => $key,
            'expires' => $expires,
            'value' => $value
        ], true) . ';';

        return $output;
    }

    protected function loadFileContent(
        FileInterface $file
    ): ?array {
        try {
            $data = require (string)$file;
        } catch (Throwable $e) {
            return null;
        }

        if (
            is_null($data) ||
            !is_array($data)
        ) {
            return null;
        }

        /** @var array{'namespace': string, 'key': string, 'expires': ?int, 'value': mixed} */
        return $data;
    }
}
