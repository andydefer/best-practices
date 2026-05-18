<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Feature\Repositories;

use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;
use AndyDefer\BestPractices\Records\Repositories\PaginateRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserFiltersRecord;
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
        $record = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
        );

        $user = $this->repository->create($record);

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertNotNull($user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame(TestUserStatus::ACTIVE, $user->status);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => 'user',
            'grade' => 1,
        ]);
    }

    public function test_find_returns_model_when_exists(): void
    {
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $result = $this->repository->find($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('Jane Doe', $result->name);
        $this->assertSame('jane@example.com', $result->email);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        $result = $this->repository->find(999);
        $this->assertNull($result);
    }

    public function test_find_by_returns_collection_with_filters_and_limit(): void
    {
        TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: 'name',
            sortDir: 'asc',
        );

        $result = $this->repository->findBy($findByRecord);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    public function test_find_by_returns_all_without_limit_when_limit_is_null(): void
    {
        TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $findByRecord = new FindByRecord(
            filters: new EmptyRecord,
            limit: null,
        );

        $result = $this->repository->findBy($findByRecord);
        $this->assertCount(2, $result);
    }

    public function test_update_updates_only_non_null_fields_and_returns_updated_model(): void
    {
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $updateRecord = new TestUserRecord(
            name: 'Updated Name',
        );

        $updated = $this->repository->update($user->id, $updateRecord);

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
        $updateRecord = new TestUserRecord(name: 'New Name');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AndyDefer\BestPractices\Tests\Fixtures\Models\TestUser with id 999 not found');

        $this->repository->update(999, $updateRecord);
    }

    public function test_delete_returns_true_when_user_exists(): void
    {
        $user = TestUser::create([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $result = $this->repository->delete($user->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_users', ['id' => $user->id]);
    }

    public function test_delete_returns_false_when_user_not_exists(): void
    {
        $result = $this->repository->delete(999);
        $this->assertFalse($result);
    }

    public function test_count_returns_total_without_criteria(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $count = $this->repository->count();
        $this->assertSame(2, $count);
    }

    public function test_count_returns_total_with_criteria(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $count = $this->repository->count($criteria);
        $this->assertSame(1, $count);
    }

    public function test_exists_returns_true_when_criteria_matches(): void
    {
        TestUser::create([
            'name' => 'Existing User',
            'email' => 'exists@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(email: 'exists@example.com');
        $exists = $this->repository->exists($criteria);

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_criteria_not_matches(): void
    {
        $criteria = new TestUserFiltersRecord(email: 'notexists@example.com');
        $exists = $this->repository->exists($criteria);

        $this->assertFalse($exists);
    }

    public function test_paginate_returns_paginated_results_with_filters_and_sorting(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'status' => $i <= 5 ? TestUserStatus::ACTIVE : TestUserStatus::INACTIVE,
                'role' => TestUserRole::USER,
                'grade' => TestUserGrade::BRONZE,
            ]);
        }

        $filters = new TestUserFiltersRecord(status: TestUserStatus::ACTIVE);
        $paginateRecord = new PaginateRecord(
            perPage: 3,
            page: 1,
            sortBy: 'name',
            sortDir: 'asc',
            filters: $filters,
        );

        /** @var Collection|\Countable $result */
        $result = $this->repository->paginate($paginateRecord);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->count());
        $this->assertSame(5, $result->total());
        $this->assertSame(1, $result->currentPage());

        $items = $result->items();
        $this->assertSame('User 1', $items[0]->name);
    }

    public function test_delete_bulk_deletes_multiple_models_matching_criteria(): void
    {
        TestUser::create([
            'name' => 'To Delete 1',
            'email' => 'todelete1@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'To Delete 2',
            'email' => 'todelete2@example.com',
            'status' => TestUserStatus::INACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);
        TestUser::create([
            'name' => 'Keep',
            'email' => 'keep@example.com',
            'status' => TestUserStatus::ACTIVE,
            'role' => TestUserRole::USER,
            'grade' => TestUserGrade::BRONZE,
        ]);

        $criteria = new TestUserFiltersRecord(status: TestUserStatus::INACTIVE);
        $deletedCount = $this->repository->deleteBulk($criteria);

        $this->assertSame(2, $deletedCount);
        $this->assertDatabaseCount('test_users', 1);
        $this->assertDatabaseHas('test_users', ['email' => 'keep@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete1@example.com']);
        $this->assertDatabaseMissing('test_users', ['email' => 'todelete2@example.com']);
    }
}
