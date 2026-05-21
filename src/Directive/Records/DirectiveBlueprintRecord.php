<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;

final class DirectiveBlueprintRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $class,
        public readonly string $signature,
        public readonly string $description,
    ) {}
}
