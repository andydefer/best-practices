<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Feature\Repositories;

use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;
use AndyDefer\BestPractices\Records\Repositories\PaginateRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserCreateRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserCriteriaRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserUpdateRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Repositories\TestUserRepository;
use AndyDefer\BestPractices\Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

final class AbstractRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TestUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TestUserRepository;
    }

    public function test_create_returns_model_and_persists_to_database(): void
    {
        // Arrange
        $record = new TestUserCreateRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act
        $user = $this->repository->create($record);

        // Assert
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame(TestBackedStringEnum::ACTIVE, $user->status);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);
    }

    public function test_find_returns_model_when_exists(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'active',
        ]);

        // Act
        $result = $this->repository->find($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('Jane Doe', $result->name);
        $this->assertSame('jane@example.com', $result->email);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        // Act
        $result = $this->repository->find(999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_returns_collection_with_filters_and_limit(): void
    {
        // Arrange
        $model = TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => 'active',
        ]);
        TestUser::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'status' => 'inactive',
        ]);

        $filters = new TestUserCriteriaRecord(status: TestBackedStringEnum::ACTIVE);
        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: 'name',
            sortDir: 'asc',
        );

        // Act
        $result = $this->repository->findBy($findByRecord);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    public function test_find_by_returns_all_without_limit_when_limit_is_null(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);
        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => 'active',
        ]);

        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            limit: null,
        );

        // Act
        $result = $this->repository->findBy($findByRecord);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_update_updates_only_non_null_fields_and_returns_updated_model(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => 'active',
        ]);

        $updateRecord = new TestUserUpdateRecord(
            name: 'Updated Name',
            email: null,
        );

        // Act
        $updated = $this->repository->update($user->id, $updateRecord);

        // Assert
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
        ]);
    }

    public function test_update_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $updateRecord = new TestUserUpdateRecord(name: 'New Name');

        // Expect
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser with id 999 not found');

        // Act
        $this->repository->update(999, $updateRecord);
    }

    public function test_delete_returns_true_when_user_exists(): void
    {
        // Arrange
        $user = TestUser::create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'status' => 'active',
        ]);

        // Act
        $result = $this->repository->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_users', ['id' => $user->id]);
    }

    public function test_delete_returns_false_when_user_not_exists(): void
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_count_returns_total_without_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => 'active',
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => 'inactive',
        ]);

        // Act
        $count = $this->repository->count();

        // Assert
        $this->assertSame(2, $count);
    }

    public function test_count_returns_total_with_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => 'active',
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => 'inactive',
        ]);

        $criteria = new TestUserCriteriaRecord(status: TestBackedStringEnum::ACTIVE);

        // Act
        $count = $this->repository->count($criteria);

        // Assert
        $this->assertSame(1, $count);
    }

    public function test_exists_returns_true_when_criteria_matches(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'Existing User',
            'email' => 'exists@example.com',
            'status' => 'active',
        ]);

        $criteria = new TestUserCriteriaRecord(email: 'exists@example.com');

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_criteria_not_matches(): void
    {
        // Arrange
        $criteria = new TestUserCriteriaRecord(email: 'notexists@example.com');

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_paginate_returns_paginated_results_with_filters_and_sorting(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'status' => $i <= 5 ? 'active' : 'inactive',
            ]);
        }

        $filters = new TestUserCriteriaRecord(status: TestBackedStringEnum::ACTIVE);
        $paginateRecord = new PaginateRecord(
            perPage: 3,
            page: 1,
            sortBy: 'name',
            sortDir: 'asc',
            filters: $filters,
        );

        // Act
        /** @var Collection|\Countable $result */
        $result = $this->repository->paginate($paginateRecord);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->count()); // count() existe sur LengthAwarePaginator
        $this->assertSame(5, $result->total());
        $this->assertSame(1, $result->currentPage());

        $items = $result->items();
        $this->assertSame('User 1', $items[0]->name);
    }

    public function test_create_bulk_creates_multiple_models(): void
    {
        // Arrange
        $records = [
            new TestUserCreateRecord(name: 'Bulk 1', email: 'bulk1@example.com', status: TestBackedStringEnum::ACTIVE),
            new TestUserCreateRecord(name: 'Bulk 2', email: 'bulk2@example.com', status: TestBackedStringEnum::ACTIVE),
            new TestUserCreateRecord(name: 'Bulk 3', email: 'bulk3@example.com', status: TestBackedStringEnum::ACTIVE),
        ];

        // Act
        $users = $this->repository->createBulk($records);

        // Assert
        $this->assertCount(3, $users);
        $this->assertDatabaseCount('test_users', 3);
        $this->assertDatabaseHas('test_users', ['email' => 'bulk1@example.com']);
        $this->assertDatabaseHas('test_users', ['email' => 'bulk2@example.com']);
        $this->assertDatabaseHas('test_users', ['email' => 'bulk3@example.com']);
    }

    public function test_delete_bulk_deletes_multiple_models_matching_criteria(): void
    {
        // Arrange
        TestUser::create([
            'name' => 'To Delete 1',
            'email' => 'todelete1@example.com',
            'status' => 'inactive',
        ]);
        TestUser::create([
            'name' => 'To Delete 2',
            'email' => 'todelete2@example.com',
            'status' => 'inactive',
        ]);
        TestUser::create([
            'name' => 'Keep',
            'email' => 'keep@example.com',
            'status' => 'active',
        ]);

        $criteria = new TestUserCriteriaRecord(status: TestBackedStringEnum::INACTIVE);

        // Act
        $deletedCount = $this->repository->deleteBulk($criteria);

        // Assert
        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseCount('test_users', 1);
        $this->assertDatabaseHas('test_users', ['email' => 'keep@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete1@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete2@example.com']);
    }
}
