<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Directive\Enums\DirectiveEventType;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class DirectiveLogRecord extends AbstractRecord
{
    public function __construct(
        public readonly DirectiveEventType $type,
        public readonly string $signature,
        public readonly ?string $class = null,
        public readonly ?ExitCode $exitCode = null,
    ) {}
}
