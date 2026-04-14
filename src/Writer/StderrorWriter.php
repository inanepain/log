<?php

/**
 * Inane: Log
 *
 * Logging.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
 *
 * @author   Philip Michael Raab<philip@cathedral.co.za>
 * @package  inanepain\log
 * @category log
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types=1);

namespace Inane\Log\Writer;

use Inane\Log\AbstractWriter;
use Inane\Stdlib\Json;

use function fwrite;
use function gmdate;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;
use const STDERR;

/**
 * Stderror Writer
 *
 * @package Inane\Log\Writer
 */
class StderrorWriter extends AbstractWriter {
    /**
     * Actually write the log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function doWrite(mixed $level, string $message, array $context = []): void {
        $entry = $this->buildEntry($level, $message, $context);

        $json = Json::encode($entry, ['numeric' => true, 'flags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);

        if ($json === false) {
            $json = Json::encode([
                'ts'    => gmdate('c'),
                'level' => 'ERROR',
                'msg'   => 'Failed to encode log entry',
            ], ['numeric' => true, 'flags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
        }

        fwrite(STDERR, $json . PHP_EOL);
    }
}
