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

use Psr\Log\LogLevel;

use function get_class;
use function gmdate;
use function is_object;
use function is_scalar;
use function method_exists;
use function strtoupper;
use function strtr;

/**
 * Abstract Writer
 *
 * @package Inane\Log
 */
abstract class AbstractWriter implements Writer {
    /**
     * @var array<string, int> PSR-3 levels to Monolog-like values
     */
    protected const array LEVELS = [
        LogLevel::EMERGENCY => 600,
        LogLevel::ALERT     => 550,
        LogLevel::CRITICAL  => 500,
        LogLevel::ERROR     => 400,
        LogLevel::WARNING   => 300,
        LogLevel::NOTICE    => 250,
        LogLevel::INFO      => 200,
        LogLevel::DEBUG     => 100,
    ];

    /**
     * @var int|null Minimum level to log
     */
    protected ?int $minLevel = null;

    /**
     * @var int|null Maximum level to log
     */
    protected ?int $maxLevel = null;

    /**
     * Set minimum level to log (at least this level)
     *
     * @param mixed $level
     *
     * @return static
     */
    public function setMinLevel(mixed $level): static {
        $this->minLevel = $this->levelToInt($level);
        return $this;
    }

    /**
     * Set maximum level to log (no more than this level)
     *
     * @param mixed $level
     *
     * @return static
     */
    public function setMaxLevel(mixed $level): static {
        $this->maxLevel = $this->levelToInt($level);
        return $this;
    }

    /**
     * Write a log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function write(mixed $level, string $message, array $context = []): void {
        if ($this->shouldWrite($level)) {
            $this->doWrite($level, $message, $context);
        }
    }

    /**
     * Actually write the log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    abstract protected function doWrite(mixed $level, string $message, array $context = []): void;

    /**
     * Check if the level should be written
     *
     * @param mixed $level
     *
     * @return bool
     */
    protected function shouldWrite(mixed $level): bool {
        $l = $this->levelToInt($level);

        if ($this->minLevel !== null && $l < $this->minLevel) {
            return false;
        }

        if ($this->maxLevel !== null && $l > $this->maxLevel) {
            return false;
        }

        return true;
    }

    /**
     * Convert level to integer
     *
     * @param mixed $level
     *
     * @return int
     */
    protected function levelToInt(mixed $level): int {
        if (isset(self::LEVELS[(string)$level])) {
            return self::LEVELS[(string)$level];
        }

        return 100; // Default to lowest
    }

    /**
     * Build log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return array
     */
    protected function buildEntry(mixed $level, string $message, array $context): array {
        $entry = [
            'ts'    => gmdate('c'),
            'level' => strtoupper((string)$level),
            'msg'   => $this->interpolate($message, $context),
        ];

        foreach ($context as $k => $v) {
            if ($k === 'exception' && $v instanceof \Throwable) {
                $entry['exception'] = [
                    'class' => get_class($v),
                    'msg'   => $v->getMessage(),
                    'file'  => $v->getFile(),
                    'line'  => $v->getLine(),
                ];
                continue;
            }

            $entry[$k] = $v;
        }

        return $entry;
    }

    /**
     * Interpolate log message
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    protected function interpolate(string $message, array $context): string {
        $replace = [];

        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string)$val;
            }
        }

        return strtr($message, $replace);
    }
}
