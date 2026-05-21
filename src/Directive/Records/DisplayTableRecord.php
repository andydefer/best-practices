<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;

final class DisplayTableRecord extends AbstractRecord
{
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}
}
