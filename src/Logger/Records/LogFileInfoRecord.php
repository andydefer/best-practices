<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;

final class LogFileInfoRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $date,
        public readonly string $hour,
        public readonly string $path,
        public readonly int $size,
        public readonly int $lines,
    ) {}
}
