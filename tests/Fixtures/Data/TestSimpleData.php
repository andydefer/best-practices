<?php

namespace AndyDefer\BestPractices\Tests\Fixtures\Data;

use AndyDefer\BestPractices\Data\AbstractData;

final class TestSimpleData extends AbstractData
{
    public function __construct(
        public readonly string $value,
    ) {}
}
