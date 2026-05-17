<?php

namespace AndyDefer\BestPractices\Tests\Fixtures\Repositories;

use AndyDefer\BestPractices\Repositories\AbstractRepository;
use AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser;

final class TestUserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return TestUser::class;
    }
}
