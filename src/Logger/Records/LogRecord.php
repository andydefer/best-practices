<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Records\Recordable;

final class LogRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $time,
        public readonly LogLevel $level,
        public readonly Recordable $data,
    ) {}
}
