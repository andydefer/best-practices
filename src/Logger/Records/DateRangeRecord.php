<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class DateRangeRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly TypedRecords $dates,
    ) {}
}
