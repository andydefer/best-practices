<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Data;

use AndyDefer\BestPractices\Data\AbstractData;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestSimpleData;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestUserData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestPureEnum;
use AndyDefer\BestPractices\Tests\TestCase;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;

final class AbstractDataTest extends TestCase
{
    // ============================================================================
    // toArray() Tests
    // ============================================================================

    public function test_to_array_preserves_camel_case_keys_when_converting_to_array(): void
    {
        // Arrange
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::parse('2024-01-15 10:30:00'),
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('emailVerifiedAt', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayNotHasKey('email_verified_at', $array);
        $this->assertArrayNotHasKey('created_at', $array);
    }

    public function test_to_array_converts_backed_enum_to_string_value_when_converting_to_array(): void
    {
        // Arrange
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::parse('2024-01-15 10:30:00'),
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertSame('active', $array['status']);
    }

    public function test_to_array_converts_pure_enum_to_enum_name_when_converting_to_array(): void
    {
        // Arrange: Create an anonymous data class with a pure enum property
        $data = new class(TestPureEnum::ADMIN) extends AbstractData
        {
            public function __construct(
                public readonly TestPureEnum $status,
            ) {}
        };

        // Act: Convert the data object to an array
        $array = $data->toArray();

        // Assert: Verify the pure enum is converted to its case name
        $this->assertSame('ADMIN', $array['status']);
    }

    public function test_to_array_converts_datetime_to_iso_8601_format_when_converting_to_array(): void
    {
        // Arrange
        $dateTime = Carbon::parse('2024-01-15 14:30:00', 'UTC');
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: $dateTime,
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $array['createdAt']);
    }

    public function test_to_array_keeps_null_values_in_array_when_converting(): void
    {
        // Arrange
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::now(),
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertNull($array['emailVerifiedAt']);
        $this->assertArrayHasKey('emailVerifiedAt', $array);
    }

    public function test_to_array_recursively_converts_nested_data_objects_when_converting(): void
    {
        // Arrange
        $child = new TestUserData(
            id: 2,
            name: 'Jane Doe',
            status: TestBackedStringEnum::INACTIVE,
            emailVerifiedAt: '2024-01-10T12:00:00Z',
            createdAt: Carbon::parse('2024-01-10 08:00:00'),
        );

        $parent = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::parse('2024-01-15 10:30:00'),
            child: $child,
        );

        // Act
        $array = $parent->toArray();

        // Assert
        $this->assertIsArray($array['child']);
        $this->assertSame(2, $array['child']['id']);
        $this->assertSame('inactive', $array['child']['status']);
    }

    public function test_to_array_recursively_converts_arrays_of_data_objects_when_converting(): void
    {
        // Arrange
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::now(),
            tags: [
                new TestSimpleData('tag1'),
                new TestSimpleData('tag2'),
            ],
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertIsArray($array['tags']);
        $this->assertCount(2, $array['tags']);
        $this->assertSame('tag1', $array['tags'][0]['value']);
        $this->assertSame('tag2', $array['tags'][1]['value']);
    }

    public function test_to_array_converts_collection_to_array_when_converting(): void
    {
        // Arrange
        $collection = new Collection([
            new TestSimpleData('item1'),
            new TestSimpleData('item2'),
        ]);

        $data = new class($collection) extends AbstractData
        {
            public function __construct(
                public readonly Collection $items,
            ) {}
        };

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertIsArray($array['items']);
        $this->assertCount(2, $array['items']);
        $this->assertSame('item1', $array['items'][0]['value']);
        $this->assertSame('item2', $array['items'][1]['value']);
    }

    public function test_to_array_preserves_arrays_of_scalars_when_converting(): void
    {
        // Arrange
        $expectedTags = ['tag1', 'tag2', 'tag3'];

        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::now(),
            tags: $expectedTags,
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertSame($expectedTags, $array['tags']);
    }

    public function test_to_array_converts_arrays_of_enums_to_their_values_when_converting(): void
    {
        // Arrange
        $data = new class([TestBackedStringEnum::ACTIVE, TestBackedStringEnum::INACTIVE]) extends AbstractData
        {
            public function __construct(
                public readonly array $roles,
            ) {}
        };

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertSame(['active', 'inactive'], $array['roles']);
    }

    public function test_to_array_returns_null_for_nullable_array_when_null(): void
    {
        // Arrange
        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::now(),
            metadata: null,
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertNull($array['metadata']);
    }

    public function test_to_array_returns_array_for_nullable_array_when_not_null(): void
    {
        // Arrange
        $expectedMetadata = ['key' => 'value'];

        $data = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: null,
            createdAt: Carbon::now(),
            metadata: $expectedMetadata,
        );

        // Act
        $array = $data->toArray();

        // Assert
        $this->assertSame($expectedMetadata, $array['metadata']);
    }

    // ============================================================================
    // collect() Tests
    // ============================================================================

    public function test_collect_creates_data_objects_from_objects_array(): void
    {
        // Arrange
        $user1 = $this->createUserObject(1, 'John Doe', TestBackedStringEnum::ACTIVE);
        $user2 = $this->createUserObject(2, 'Jane Doe', TestBackedStringEnum::INACTIVE);
        $users = [$user1, $user2];

        // Act
        $result = TestUserData::collect($users);

        // Assert
        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUserData::class, $result[0]);
        $this->assertInstanceOf(TestUserData::class, $result[1]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('John Doe', $result[0]->name);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame('Jane Doe', $result[1]->name);
    }

    public function test_collect_creates_data_objects_from_arrays(): void
    {
        // Arrange
        $users = [
            [
                'id' => 1,
                'name' => 'John Doe',
                'status' => TestBackedStringEnum::ACTIVE,
                'emailVerifiedAt' => null,
                'createdAt' => Carbon::parse('2024-01-15 10:30:00'),
                'tags' => [],
                'metadata' => null,
            ],
            [
                'id' => 2,
                'name' => 'Jane Doe',
                'status' => TestBackedStringEnum::INACTIVE,
                'emailVerifiedAt' => '2024-01-10T12:00:00Z',
                'createdAt' => Carbon::parse('2024-01-10 08:00:00'),
                'tags' => [],
                'metadata' => null,
            ],
        ];

        // Act
        $result = TestUserData::collect($users);

        // Assert
        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUserData::class, $result[0]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('John Doe', $result[0]->name);
    }

    public function test_collect_returns_empty_array_when_input_is_empty(): void
    {
        // Arrange & Act
        $result = TestUserData::collect([]);

        // Assert
        $this->assertSame([], $result);
    }

    public function test_collect_throws_exception_when_item_is_not_object_or_array(): void
    {
        // Arrange
        $invalidInput = ['invalid'];

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item must be an object or array, string given');

        // Act
        TestUserData::collect($invalidInput);
    }

    public function test_collect_creates_data_objects_from_laravel_collection(): void
    {
        // Arrange
        $userObjects = collect([
            $this->createUserObject(1, 'John Doe', TestBackedStringEnum::ACTIVE),
            $this->createUserObject(2, 'Jane Doe', TestBackedStringEnum::INACTIVE),
        ]);

        // Act
        $result = TestUserData::collect($userObjects);

        // Assert
        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestUserData::class, $result[0]);
        $this->assertInstanceOf(TestUserData::class, $result[1]);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Create a test user object for collect() tests.
     */
    private function createUserObject(int $id, string $name, TestBackedStringEnum $status): object
    {
        return new class($id, $name, $status)
        {
            public int $id;

            public string $name;

            public TestBackedStringEnum $status;

            public ?string $emailVerifiedAt = null;

            public DateTime $createdAt;

            public array $tags = [];

            public ?array $metadata = null;

            public function __construct(int $id, string $name, TestBackedStringEnum $status)
            {
                $this->id = $id;
                $this->name = $name;
                $this->status = $status;
                $this->createdAt = Carbon::now();
            }
        };
    }
}
