<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class DisplayMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $message,
        public readonly MessageType $type,
    ) {}
}
