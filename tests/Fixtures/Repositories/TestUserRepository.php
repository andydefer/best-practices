<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Repositories;

use AndyDefer\BestPractices\Repositories\AbstractRepository;
use AndyDefer\BestPractices\Records\Repositories\RepositoryInfoRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserRecord;

final class TestUserRepository extends AbstractRepository
{
    public function info(): RepositoryInfoRecord
    {
        return new RepositoryInfoRecord(
            modelClass: TestUser::class,
            recordClass: TestUserRecord::class,
        );
    }
}
