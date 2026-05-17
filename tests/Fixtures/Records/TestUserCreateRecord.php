<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;

final class TestUserCreateRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TestBackedStringEnum $status = TestBackedStringEnum::ACTIVE,
    ) {}
}
