<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class ParsedDirectiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedRecords $arguments,
        public readonly TypedRecords $options,
    ) {}
}
