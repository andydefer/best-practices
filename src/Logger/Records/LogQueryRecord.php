<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class LogQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $type = null,
        public readonly ?LogLevel $level = null,
    ) {}
}
