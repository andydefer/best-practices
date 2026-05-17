<?php

namespace AndyDefer\BestPractices\Tests\Fixtures\Data;

use AndyDefer\BestPractices\Data\AbstractData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use DateTime;

final class TestUserData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly TestBackedStringEnum $status,
        public readonly ?string $emailVerifiedAt,
        public readonly DateTime $createdAt,
        public readonly ?TestUserData $child = null,
        public readonly array $tags = [],
        public readonly ?array $metadata = null,
    ) {}
}
