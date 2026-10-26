<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger keeping records in memory
 */
class MemoryLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = (is_string($level) ? $level : 'unknown') . ': ' . $message;
    }
}
