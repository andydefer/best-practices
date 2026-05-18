<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Data;

use AndyDefer\BestPractices\Data\AbstractData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;

final class TestFullUserData extends AbstractData
{
    /**
     * @param  array<int, TestProductData>  $products
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly TestUserStatus $status,
        public readonly TestUserRole $role,
        public readonly TestUserGrade $grade,
        public readonly ?string $emailVerifiedAt,
        public readonly array $tags,
        public readonly string $createdAt,
        public readonly array $products = [],
        public readonly ?TestProductData $featuredProduct = null,
        public readonly ?self $child = null,
    ) {}
}
