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

namespace Inane\Log;

/**
 * Writer Interface
 *
 * @package Inane\Log
 */
interface Writer {
    /**
     * Write a log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function write(mixed $level, string $message, array $context = []): void;
}
