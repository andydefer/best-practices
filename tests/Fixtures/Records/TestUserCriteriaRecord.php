<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;

final class TestUserCriteriaRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestBackedStringEnum $status = null,
    ) {}
}
