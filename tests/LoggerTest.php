<?php

/**
 * Inane: Log Tests
 */

declare(strict_types=1);

namespace Inane\Log\Tests;

use Inane\Log\AbstractWriter;
use Inane\Log\Logger;
use Inane\Log\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase {
    public function testLoggerDispatchesToAllWriters(): void {
        $calls = [];

        $w1 = new class($calls) implements Writer {
            public $calls;
            public function __construct(array &$calls) { $this->calls = &$calls; }
            public function write(mixed $level, string $message, array $context = []): void {
                $this->calls[] = ['w' => 1, 'l' => $level, 'm' => $message, 'c' => $context];
            }
        };

        $w2 = new class($calls) implements Writer {
            public $calls;
            public function __construct(array &$calls) { $this->calls = &$calls; }
            public function write(mixed $level, string $message, array $context = []): void {
                $this->calls[] = ['w' => 2, 'l' => $level, 'm' => $message, 'c' => $context];
            }
        };

        $logger = new Logger([$w1, $w2]);
        $logger->log(LogLevel::INFO, 'Hello {name}', ['name' => 'World']);

        $this->assertCount(2, $calls);
        $this->assertSame(LogLevel::INFO, $calls[0]['l']);
        $this->assertSame('Hello {name}', $calls[0]['m']);
        $this->assertSame(['name' => 'World'], $calls[0]['c']);
        $this->assertSame(1, $calls[0]['w']);
        $this->assertSame(2, $calls[1]['w']);
    }
}

#[CoversClass(AbstractWriter::class)]
final class AbstractWriterTest extends TestCase {
    public function testMinAndMaxLevelFiltering(): void {
        $written = [];

        $writer = new class($written) extends AbstractWriter {
            public $written;
            public function __construct(array &$written) { $this->written = &$written; }
            protected function doWrite(mixed $level, string $message, array $context = []): void {
                $this->written[] = [$level, $message, $context];
            }
            // Expose helpers for testing
            public function build(mixed $level, string $message, array $context): array { return $this->buildEntry($level, $message, $context); }
            public function interp(string $message, array $context): string { $r = new \ReflectionClass($this); $m = $r->getMethod('interpolate'); $m->setAccessible(true); return $m->invoke($this, $message, $context); }
        };

        // Only WARNING..ERROR should pass
        $writer->setMinLevel(LogLevel::WARNING)->setMaxLevel(LogLevel::ERROR);

        $writer->write(LogLevel::DEBUG, 'd');   // filtered out
        $writer->write(LogLevel::INFO, 'i');    // filtered out
        $writer->write(LogLevel::NOTICE, 'n');  // filtered out
        $writer->write(LogLevel::WARNING, 'w'); // in
        $writer->write(LogLevel::ERROR, 'e');   // in
        $writer->write(LogLevel::CRITICAL, 'c'); // filtered out

        $this->assertSame([ [LogLevel::WARNING, 'w', []], [LogLevel::ERROR, 'e', []] ], $written);

        // Check buildEntry + interpolate basic behavior
        $entry = $writer->build(LogLevel::INFO, 'Hello {name}', ['name' => 'Bob', 'extra' => 123]);
        $this->assertArrayHasKey('ts', $entry);
        $this->assertSame('INFO', $entry['level']);
        $this->assertSame('Hello Bob', $entry['msg']);
        $this->assertSame(123, $entry['extra']);
    }

    public function testExceptionInContextIsExpanded(): void {
        $written = [];
        $writer = new class($written) extends AbstractWriter {
            public $written;
            public function __construct(array &$written) { $this->written = &$written; }
            protected function doWrite(mixed $level, string $message, array $context = []): void {
                $this->written[] = $this->buildEntry($level, $message, $context);
            }
        };

        $ex = new \RuntimeException('Boom');
        $writer->write(LogLevel::ERROR, 'Fail', ['exception' => $ex]);

        $this->assertCount(1, $written);
        $entry = $written[0];
        $this->assertSame('ERROR', $entry['level']);
        $this->assertArrayHasKey('exception', $entry);
        $this->assertSame(\RuntimeException::class, $entry['exception']['class']);
        $this->assertSame('Boom', $entry['exception']['msg']);
        $this->assertArrayHasKey('file', $entry['exception']);
        $this->assertArrayHasKey('line', $entry['exception']);
    }
}
