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

declare(strict_types = 1);

namespace Inane\Log\Writer;

use Inane\Log\AbstractWriter;
use Inane\Stdlib\Exception\JsonException;
use Inane\Stdlib\Json;

use function clearstatcache;
use function fclose;
use function file_exists;
use function filesize;
use function flock;
use function fopen;
use function fwrite;
use function gmdate;
use function is_dir;
use function mkdir;
use function rename;
use function sprintf;
use function str_replace;
use function unlink;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
use const LOCK_UN;
use const PHP_EOL;

/**
 * Json File Writer
 *
 * @package Inane\Log\Writer
 */
class JsonFileWriter extends AbstractWriter {
    /**
     * Create writer
     *
     * @param string $dir
     * @param string $baseName
     * @param int    $maxSizeBytes
     * @param int    $maxFiles
     * @param string $dateFormat
     */
    public function __construct(
        /**
         * @var string Log directory
         */
        private readonly string $dir = 'logs',
        /**
         * @var string Base filename
         */
        private readonly string $baseName = 'output',
        /**
         * @var int Max file size in bytes
         */
        private readonly int    $maxSizeBytes = 5_000_000,
        /**
         * @var int Max number of log files to keep
         */
        private readonly int    $maxFiles = 5,
        /**
         * @var string Date format for log filename
         */
        private readonly string $dateFormat = 'Y-m-d',
    ) {
        if (!is_dir($this->dir)) {
            if (!mkdir($concurrentDirectory = $this->dir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
    }

    public static function jsonEncode(array $entry): string {
        $json = Json::encode($entry, [
            'numeric' => true,
            'flags'   => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ]);

        if ($json === false) {
            $json = Json::encode([
                'ts'    => gmdate('c'),
                'level' => 'ERROR',
                'msg'   => 'Failed to encode log entry',
            ], [
                'numeric' => true,
                'flags'   => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ]);
        }

        return $json;
    }

    /**
     * Actually write the log entry
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     * @throws JsonException
     */
    protected function doWrite(mixed $level, string $message, array $context = []): void {
        $file = $this->getLogFile();
        $this->rotateIfNeeded($file);

        $entry = $this->buildEntry($level, $message, $context);
        $json = self::jsonEncode($entry) . PHP_EOL;

        $fp = fopen($file, 'ab');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $json);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Get log file path
     *
     * @return string
     */
    private function getLogFile(): string {
        return "{$this->dir}/{$this->baseName}.log";
    }

    /**
     * Rotate log file if needed
     *
     * @param string $file
     *
     * @return void
     */
    private function rotateIfNeeded(string $file): void {
        if (!file_exists($file)) {
            return;
        }

        clearstatcache(true, $file);

        if (filesize($file) < $this->maxSizeBytes) {
            return;
        }

        $date = gmdate($this->dateFormat);
        rename($file, $file = str_replace('.log', "-{$date}.log", $file));

        // shift old logs
        for($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $old = $file . '.' . $i;
            $new = $file . '.' . ($i + 1);

            if (file_exists($old)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($old);
                } else {
                    rename($old, $new);
                }
            }
        }

        rename($file, $file . '.1');
    }
}
