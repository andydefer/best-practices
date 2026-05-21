<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class HydrationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $class,
        public readonly string $signature,
        public readonly string $description,
        public readonly TypedRecords $aliases,
        public readonly TypedRecords $arguments,
        public readonly TypedRecords $options,
    ) {}
}
