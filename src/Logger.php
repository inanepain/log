<?php

declare(strict_types=1);

namespace Inane\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Logger
 *
 * A PSR-3 logger that delegates to one or more writers.
 *
 * @package Inane\Log
 */
class Logger implements LoggerInterface {
    use LoggerTrait;

    /**
     * @var Writer[]
     */
    protected array $writers = [];

    /**
     * Create logger
     *
     * @param Writer[] $writers
     */
    public function __construct(array $writers = []) {
        foreach ($writers as $writer) {
            $this->addWriter($writer);
        }
    }

    /**
     * Add a writer
     *
     * @param Writer $writer
     *
     * @return self
     */
    public function addWriter(Writer $writer): self {
        $this->writers[] = $writer;
        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string|\Stringable $message
     * @param array  $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void {
        foreach ($this->writers as $writer) {
            $writer->write($level, (string)$message, $context);
        }
    }
}
