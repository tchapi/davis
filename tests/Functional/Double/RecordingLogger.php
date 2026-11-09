<?php

declare(strict_types=1);

namespace App\Tests\Functional\Double;

use Psr\Log\AbstractLogger;

/**
 * Keeps every record it is given, for assertions on what a service logged.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
