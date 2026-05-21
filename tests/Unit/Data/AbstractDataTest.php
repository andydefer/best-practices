<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Data;

use AndyDefer\BestPractices\Tests\Fixtures\Data\TestFullUserData;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestProductData;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestUserData;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestUserWithRolesData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\BestPractices\Tests\TestCase;
use Carbon\Carbon;

final class AbstractDataTest extends TestCase
{
    // ============================================================================
    // toArray() Tests
    // ============================================================================

    public function test_to_array_preserves_camel_case_keys_when_converting_to_array(): void
    {
        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            tags: [],
            createdAt: '2024-01-15T10:30:00Z',
        );

        $array = $data->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('grade', $array);
        $this->assertArrayHasKey('emailVerifiedAt', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayNotHasKey('email_verified_at', $array);
        $this->assertArrayNotHasKey('created_at', $array);
    }

    public function test_to_array_converts_backed_enum_to_string_value_when_converting_to_array(): void
    {
        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            tags: [],
            createdAt: '2024-01-15T10:30:00Z',
        );

        $array = $data->toArray();

        $this->assertSame('admin', $array['role']);
    }

    public function test_to_array_converts_int_backed_enum_to_int_value_when_converting_to_array(): void
    {
        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::GOLD,
            emailVerifiedAt: null,
            tags: [],
            createdAt: '2024-01-15T10:30:00Z',
        );

        $array = $data->toArray();

        $this->assertSame(3, $array['grade']);
    }

    public function test_to_array_converts_pure_enum_to_enum_name_when_converting_to_array(): void
    {
        $data = new TestUserWithRolesData(
            roles: [TestUserRole::ADMIN, TestUserRole::USER],
        );

        $array = $data->toArray();

        $this->assertSame(['admin', 'user'], $array['roles']);
    }

    public function test_to_array_converts_datetime_to_iso_8601_format_when_converting_to_array(): void
    {
        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            tags: [],
            createdAt: '2024-01-15T14:30:00Z',
        );

        $array = $data->toArray();

        $this->assertSame('2024-01-15T14:30:00Z', $array['createdAt']);
    }

    public function test_to_array_keeps_null_values_in_array_when_converting(): void
    {
        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            tags: [],
            createdAt: '2024-01-15T14:30:00Z',
        );

        $array = $data->toArray();

        $this->assertNull($array['emailVerifiedAt']);
        $this->assertArrayHasKey('emailVerifiedAt', $array);
    }

    public function test_to_array_recursively_converts_nested_data_objects_when_converting(): void
    {
        $childProduct = new TestProductData(
            id: '2',
            name: 'Child Product',
            price: 100,
            isFeatured: true,
            createdAt: '2024-01-10T08:00:00Z',
        );

        $child = new TestFullUserData(
            id: '2',
            name: 'Jane Doe',
            email: 'jane@example.com',
            status: TestUserStatus::INACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::SILVER,
            emailVerifiedAt: '2024-01-10T12:00:00Z',
            createdAt: '2024-01-10T08:00:00Z',
            tags: [],
            products: [new TestProductData(id: '3', name: 'Jane Product', price: 50, createdAt: '2024-01-10T08:00:00Z')],
            featuredProduct: $childProduct,
        );

        $parentProduct = new TestProductData(
            id: '4',
            name: 'Parent Product',
            price: 500,
            isFeatured: true,
            createdAt: '2024-01-15T10:30:00Z',
        );

        $parent = new TestFullUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
            grade: TestUserGrade::GOLD,
            emailVerifiedAt: null,
            createdAt: '2024-01-15T10:30:00Z',
            tags: [],
            products: [new TestProductData(id: '5', name: 'Parent Product 1', price: 200, createdAt: '2024-01-15T10:30:00Z')],
            featuredProduct: $parentProduct,
            child: $child,
        );

        $array = $parent->toArray();

        $this->assertSame('1', $array['id']);
        $this->assertSame('John Doe', $array['name']);
        $this->assertSame('admin', $array['role']);
        $this->assertSame(3, $array['grade']);

        $this->assertIsArray($array['products']);
        $this->assertCount(1, $array['products']);
        $this->assertSame('Parent Product 1', $array['products'][0]['name']);
        $this->assertSame(200, $array['products'][0]['price']);

        $this->assertIsArray($array['featuredProduct']);
        $this->assertSame('Parent Product', $array['featuredProduct']['name']);
        $this->assertSame(500, $array['featuredProduct']['price']);
        $this->assertTrue($array['featuredProduct']['isFeatured']);

        $this->assertIsArray($array['child']);
        $this->assertSame('2', $array['child']['id']);
        $this->assertSame('user', $array['child']['role']);

        $this->assertIsArray($array['child']['products']);
        $this->assertCount(1, $array['child']['products']);
        $this->assertSame('Jane Product', $array['child']['products'][0]['name']);

        $this->assertIsArray($array['child']['featuredProduct']);
        $this->assertSame('Child Product', $array['child']['featuredProduct']['name']);
        $this->assertSame(100, $array['child']['featuredProduct']['price']);
        $this->assertTrue($array['child']['featuredProduct']['isFeatured']);
    }

    public function test_to_array_recursively_converts_arrays_of_data_objects_when_converting(): void
    {
        $product1 = new TestProductData(
            id: '1',
            name: 'Laptop',
            price: 999,
            isFeatured: true,
            createdAt: '2024-01-15T10:30:00Z',
        );
        $product2 = new TestProductData(
            id: '2',
            name: 'Mouse',
            price: 29,
            isFeatured: false,
            createdAt: '2024-01-15T10:30:00Z',
        );
        $product3 = new TestProductData(
            id: '3',
            name: 'Keyboard',
            price: 89,
            isFeatured: false,
            createdAt: '2024-01-15T10:30:00Z',
        );

        $data = new TestFullUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            createdAt: '2024-01-15T10:30:00Z',
            tags: ['developer', 'laravel'],
            products: [$product1, $product2, $product3],
            featuredProduct: $product1,
        );

        $array = $data->toArray();

        $this->assertIsArray($array['tags']);
        $this->assertCount(2, $array['tags']);
        $this->assertSame(['developer', 'laravel'], $array['tags']);

        $this->assertIsArray($array['products']);
        $this->assertCount(3, $array['products']);
        $this->assertSame('Laptop', $array['products'][0]['name']);
        $this->assertSame(999, $array['products'][0]['price']);
        $this->assertTrue($array['products'][0]['isFeatured']);
        $this->assertSame('Mouse', $array['products'][1]['name']);
        $this->assertSame(29, $array['products'][1]['price']);
        $this->assertFalse($array['products'][1]['isFeatured']);
        $this->assertSame('Keyboard', $array['products'][2]['name']);
        $this->assertSame(89, $array['products'][2]['price']);
        $this->assertFalse($array['products'][2]['isFeatured']);

        $this->assertIsArray($array['featuredProduct']);
        $this->assertSame('Laptop', $array['featuredProduct']['name']);
        $this->assertSame(999, $array['featuredProduct']['price']);
        $this->assertTrue($array['featuredProduct']['isFeatured']);
    }

    public function test_to_array_converts_arrays_of_scalars_when_converting(): void
    {
        $expectedTags = ['tag1', 'tag2', 'tag3'];

        $data = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: null,
            tags: $expectedTags,
            createdAt: '2024-01-15T10:30:00Z',
        );

        $array = $data->toArray();

        $this->assertSame($expectedTags, $array['tags']);
    }

    public function test_to_array_converts_arrays_of_enums_to_their_values_when_converting(): void
    {
        $data = new TestUserWithRolesData(
            roles: [TestUserRole::ADMIN, TestUserRole::USER],
        );

        $array = $data->toArray();

        $this->assertSame(['admin', 'user'], $array['roles']);
    }

    // ============================================================================
    // collect() Tests
    // ============================================================================

    public function test_collect_creates_data_objects_from_objects_array(): void
    {
        $user1 = $this->createUserDataArray('1', 'John Doe', TestUserStatus::ACTIVE, TestUserRole::USER, TestUserGrade::BRONZE);
        $user2 = $this->createUserDataArray('2', 'Jane Doe', TestUserStatus::INACTIVE, TestUserRole::USER, TestUserGrade::BRONZE);
        $users = [$user1, $user2];

        $result = TestUserData::collect($users);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUserData::class, $result[0]);
        $this->assertInstanceOf(TestUserData::class, $result[1]);
        $this->assertSame('1', $result[0]->id);
        $this->assertSame('John Doe', $result[0]->name);
        $this->assertSame('2', $result[1]->id);
        $this->assertSame('Jane Doe', $result[1]->name);
    }

    public function test_collect_creates_data_objects_from_arrays(): void
    {
        $users = [
            [
                'id' => '1',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => TestUserStatus::ACTIVE,
                'role' => TestUserRole::USER,
                'grade' => TestUserGrade::BRONZE,
                'emailVerifiedAt' => null,
                'createdAt' => '2024-01-15T10:30:00Z',
                'tags' => [],
            ],
            [
                'id' => '2',
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'status' => TestUserStatus::INACTIVE,
                'role' => TestUserRole::USER,
                'grade' => TestUserGrade::BRONZE,
                'emailVerifiedAt' => '2024-01-10T12:00:00Z',
                'createdAt' => '2024-01-10T08:00:00Z',
                'tags' => [],
            ],
        ];

        $result = TestUserData::collect($users);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUserData::class, $result[0]);
        $this->assertSame('1', $result[0]->id);
        $this->assertSame('John Doe', $result[0]->name);
    }

    public function test_collect_returns_empty_array_when_input_is_empty(): void
    {
        $result = TestUserData::collect([]);

        $this->assertSame([], $result);
    }

    public function test_collect_throws_exception_when_item_is_not_object_or_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item must be an object or array, string given');

        TestUserData::collect(['invalid']);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Crée un tableau associatif pour TestUserData au lieu d'un stdClass
     */
    private function createUserDataArray(string $id, string $name, TestUserStatus $status, TestUserRole $role, TestUserGrade $grade): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $name.'@example.com',
            'status' => $status,
            'role' => $role,
            'grade' => $grade,
            'emailVerifiedAt' => null,
            'createdAt' => Carbon::now()->format('Y-m-d\TH:i:s\Z'),
            'tags' => [],
        ];
    }
}
