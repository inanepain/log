<?php

declare(strict_types=1);

namespace Inane\Log\Writer;

use Inane\Log\AbstractWriter;

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
     * @var string Log directory
     */
    private string $dir;

    /**
     * @var string Base filename
     */
    private string $baseName;

    /**
     * @var int Max file size in bytes
     */
    private int $maxSizeBytes;

    /**
     * @var int Max number of log files to keep
     */
    private int $maxFiles;

    /**
     * @var string Date format for log filename
     */
    private string $dateFormat;

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
        string $dir = __DIR__ . '/../logs',
        string $baseName = 'app',
        int    $maxSizeBytes = 5_000_000,
        int    $maxFiles = 5,
        string $dateFormat = 'Y-m-d',
    ) {
        $this->dir = $dir;
        $this->baseName = $baseName;
        $this->maxSizeBytes = $maxSizeBytes;
        $this->maxFiles = $maxFiles;
        $this->dateFormat = $dateFormat;

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
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
    protected function doWrite(mixed $level, string $message, array $context = []): void {
        $file = $this->getLogFile();

        $this->rotateIfNeeded($file);

        $entry = $this->buildEntry($level, $message, $context);

        $json = Json::encode($entry, ['numeric' => true, 'flags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);

        if ($json === false) {
            $json = Json::encode([
                'ts'    => gmdate('c'),
                'level' => 'ERROR',
                'msg'   => 'Failed to encode log entry',
            ], ['numeric' => true, 'flags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
        }

        $json .= PHP_EOL;

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
        $date = gmdate($this->dateFormat);

        return "{$this->dir}/{$this->baseName}-{$date}.log";
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

        // shift old logs
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
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
