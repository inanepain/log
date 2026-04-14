<?php

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
