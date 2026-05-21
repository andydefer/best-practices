<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Data;

use AndyDefer\BestPractices\Data\AbstractData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;

final class TestUserWithRolesData extends AbstractData
{
    /**
     * @param  array<int, TestUserRole>  $roles
     */
    public function __construct(
        public readonly array $roles = [],
    ) {}
}
