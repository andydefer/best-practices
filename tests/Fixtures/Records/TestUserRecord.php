<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestPureEnum;
use DateTimeInterface;

/**
 * Test record for unit tests.
 *
 * PURE RECORD - No logic, just data structure.
 * Used exclusively for testing serialization and normalization.
 */
final class TestUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?DateTimeInterface $createdAt = null,
        public readonly ?TestBackedStringEnum $status = null,
        public readonly ?TestPureEnum $role = null,
        public readonly ?array $tags = null,
        public readonly ?self $manager = null,
    ) {}
}
