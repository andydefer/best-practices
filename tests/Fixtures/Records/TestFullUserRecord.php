<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;

/**
 * Test full user record for unit tests.
 */
final class TestFullUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TestUserStatus $status = TestUserStatus::ACTIVE,
        public readonly TestUserRole $role = TestUserRole::USER,
        public readonly TestUserGrade $grade = TestUserGrade::BRONZE,
        public readonly ?string $emailVerifiedAt = null,
        public readonly TypedRecords $tags = new TypedRecords('string'),
        public readonly TypedRecords $products = new TypedRecords(TestProductRecord::class),
        public readonly ?TestProductRecord $featuredProduct = null,
    ) {}
}
