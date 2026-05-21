<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class DirectiveExecutionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $signature,
        public readonly TypedRecords $arguments,
    ) {}
}
