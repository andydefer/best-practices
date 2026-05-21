<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,
        public readonly MixedPayloadCollection $payload,
    ) {}
}
