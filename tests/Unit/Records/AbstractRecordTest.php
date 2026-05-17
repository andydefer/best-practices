<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestPureEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserRecord;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for AbstractRecord.
 *
 * Verifies the serialization and normalization behavior of AbstractRecord
 * with various data types including primitives, enums, dates, nested arrays,
 * traversable objects, and recursive records.
 */
final class AbstractRecordTest extends TestCase
{
    /**
     * Test that toArray returns correctly formatted array for simple record.
     *
     * Verifies that all public properties are converted to array
     * with keys in snake_case and primitive values preserved.
     */
    public function test_to_array_returns_array_with_snake_case_keys_for_simple_record(): void
    {
        // Arrange: Create a simple record with basic scalar values
        $record = new TestUserRecord(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify array structure with snake_case keys
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame(1, $result['id']);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John Doe', $result['name']);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('john@example.com', $result['email']);

        // Assert: Verify null values are preserved as null
        $this->assertArrayHasKey('created_at', $result);
        $this->assertNull($result['created_at']);
        $this->assertArrayHasKey('status', $result);
        $this->assertNull($result['status']);
        $this->assertArrayHasKey('role', $result);
        $this->assertNull($result['role']);
        $this->assertArrayHasKey('tags', $result);
        $this->assertNull($result['tags']);
        $this->assertArrayHasKey('manager', $result);
        $this->assertNull($result['manager']);
    }

    /**
     * Test that toArray converts DateTimeInterface to ISO 8601 format.
     *
     * Verifies that DateTime and DateTimeImmutable objects are normalized
     * to UTC ISO 8601 string format without microseconds.
     */
    public function test_to_array_converts_datetime_to_iso8601_string(): void
    {
        // Arrange: Create record with DateTime
        $dateTime = new DateTime('2024-01-15 14:30:00', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 1,
            name: 'Jane Doe',
            email: 'jane@example.com',
            createdAt: $dateTime,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify DateTime is converted to ISO 8601 format
        $this->assertIsString($result['created_at']);
        $this->assertSame('2024-01-15T14:30:00Z', $result['created_at']);
    }

    /**
     * Test that toArray converts DateTimeImmutable to ISO 8601 format.
     *
     * Verifies that immutable datetime objects are properly normalized.
     */
    public function test_to_array_converts_datetime_immutable_to_iso8601_string(): void
    {
        // Arrange: Create record with DateTimeImmutable
        $dateTimeImmutable = new DateTimeImmutable('2024-12-25 09:15:30', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 2,
            name: 'Bob Smith',
            email: 'bob@example.com',
            createdAt: $dateTimeImmutable,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify DateTimeImmutable is converted to ISO 8601 format
        $this->assertIsString($result['created_at']);
        $this->assertSame('2024-12-25T09:15:30Z', $result['created_at']);
    }

    /**
     * Test that toArray converts backed enum to its scalar value.
     *
     * Verifies that BackedEnum (string/int backed) instances are normalized
     * to their underlying scalar value.
     */
    public function test_to_array_converts_backed_enum_to_scalar_value(): void
    {
        // Arrange: Create record with backed enum
        $record = new TestUserRecord(
            id: 3,
            name: 'Alice Johnson',
            email: 'alice@example.com',
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify backed enum is converted to its value
        $this->assertArrayHasKey('status', $result);
        $this->assertIsString($result['status']);
        $this->assertSame('active', $result['status']);
    }

    /**
     * Test that toArray converts pure enum to its name.
     *
     * Verifies that pure enums (non-backed) are normalized to their case name.
     */
    public function test_to_array_converts_pure_enum_to_name(): void
    {
        // Arrange: Create record with pure enum
        $record = new TestUserRecord(
            id: 4,
            name: 'Charlie Brown',
            email: 'charlie@example.com',
            role: TestPureEnum::ADMIN,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify pure enum is converted to its name
        $this->assertArrayHasKey('role', $result);
        $this->assertIsString($result['role']);
        $this->assertSame('ADMIN', $result['role']);
    }

    /**
     * Test that toArray handles array values recursively.
     *
     * Verifies that nested arrays are processed recursively,
     * converting any nested objects (enums, dates, etc.) properly.
     */
    public function test_to_array_handles_array_values_recursively(): void
    {
        // Arrange: Create record with nested array containing various types
        $date = new DateTime('2024-01-01 10:00:00', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 5,
            name: 'David Miller',
            email: 'david@example.com',
            tags: [
                'primary',
                'vip',
                'verified',
                'nested' => [
                    'level2',
                    'date' => $date,
                    'enum' => TestBackedStringEnum::PENDING,
                ],
            ],
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify array structure
        $this->assertIsArray($result['tags']);
        $this->assertSame('primary', $result['tags'][0]);
        $this->assertSame('vip', $result['tags'][1]);
        $this->assertSame('verified', $result['tags'][2]);

        // Assert: Verify nested array is processed recursively
        $this->assertIsArray($result['tags']['nested']);
        $this->assertSame('level2', $result['tags']['nested'][0]);
        $this->assertSame('2024-01-01T10:00:00Z', $result['tags']['nested']['date']);
        $this->assertSame('pending', $result['tags']['nested']['enum']);
    }

    /**
     * Test that toArray handles nested record recursively.
     *
     * Verifies that when a record contains another record (self-referential),
     * the nested record is also converted to array recursively.
     */
    public function test_to_array_handles_nested_record_recursively(): void
    {
        // Arrange: Create nested record structure
        $manager = new TestUserRecord(
            id: 10,
            name: 'Manager Name',
            email: 'manager@example.com',
        );

        $record = new TestUserRecord(
            id: 6,
            name: 'Employee Name',
            email: 'employee@example.com',
            manager: $manager,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify manager is converted to array
        $this->assertArrayHasKey('manager', $result);
        $this->assertIsArray($result['manager']);
        $this->assertSame(10, $result['manager']['id']);
        $this->assertSame('Manager Name', $result['manager']['name']);
        $this->assertSame('manager@example.com', $result['manager']['email']);

        // Assert: Verify original record fields
        $this->assertSame(6, $result['id']);
        $this->assertSame('Employee Name', $result['name']);
        $this->assertSame('employee@example.com', $result['email']);
    }

    /**
     * Test that toArray handles null values correctly.
     *
     * Verifies that optional properties that are null remain null in the array.
     */
    public function test_to_array_handles_null_values_correctly(): void
    {
        // Arrange: Create record with all optional fields as null
        $record = new TestUserRecord(
            id: 7,
            name: 'Null Test',
            email: 'null@example.com',
            createdAt: null,
            status: null,
            role: null,
            tags: null,
            manager: null,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify all optional fields are null
        $this->assertNull($result['created_at']);
        $this->assertNull($result['status']);
        $this->assertNull($result['role']);
        $this->assertNull($result['tags']);
        $this->assertNull($result['manager']);
    }

    /**
     * Test that toArray preserves integer and string values.
     *
     * Verifies that scalar types (int, string) are preserved exactly.
     */
    public function test_to_array_preserves_scalar_types(): void
    {
        // Arrange: Create record with various scalar types
        $record = new TestUserRecord(
            id: 999,
            name: 'Scalar Test',
            email: 'scalar@example.com',
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify scalar types are preserved
        $this->assertIsInt($result['id']);
        $this->assertSame(999, $result['id']);
        $this->assertIsString($result['name']);
        $this->assertSame('Scalar Test', $result['name']);
        $this->assertIsString($result['email']);
        $this->assertSame('scalar@example.com', $result['email']);
    }

    /**
     * Test that toDatabase removes null values.
     *
     * Verifies that only non-null values are included in the database array.
     */
    public function test_to_database_removes_null_values(): void
    {
        // Arrange: Create record with some null values
        $record = new TestUserRecord(
            id: 3,
            name: 'Alice Johnson',
            email: 'alice@example.com',
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: Only non-null values are included
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('status', $result);

        // Assert: Null values are excluded
        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('role', $result);
        $this->assertArrayNotHasKey('tags', $result);
        $this->assertArrayNotHasKey('manager', $result);

        // Assert: Values are correct
        $this->assertSame(3, $result['id']);
        $this->assertSame('Alice Johnson', $result['name']);
        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('active', $result['status']);
    }

    /**
     * Test that toDatabase includes all values when none are null.
     *
     * Verifies that when all fields have non-null values, toDatabase returns
     * the same as toArray (without null values to remove).
     */
    public function test_to_database_includes_all_values_when_none_are_null(): void
    {
        // Arrange: Create record with all fields populated
        $date = new DateTime('2024-01-15 10:30:00', new \DateTimeZone('UTC'));
        $manager = new TestUserRecord(
            id: 10,
            name: 'Manager',
            email: 'manager@example.com',
        );

        $record = new TestUserRecord(
            id: 5,
            name: 'John Doe',
            email: 'john@example.com',
            createdAt: $date,
            status: TestBackedStringEnum::ACTIVE,
            role: TestPureEnum::ADMIN,
            tags: ['tag1', 'tag2'],
            manager: $manager,
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: All fields are included (none are null)
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('manager', $result);

        // Assert: Values are correct
        $this->assertSame(5, $result['id']);
        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('2024-01-15T10:30:00Z', $result['created_at']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('ADMIN', $result['role']);
        $this->assertSame(['tag1', 'tag2'], $result['tags']);
        $this->assertIsArray($result['manager']);
        $this->assertSame(10, $result['manager']['id']);
    }

    /**
     * Test that toDatabase handles nested records recursively.
     *
     * Verifies that nested records also have null values removed.
     */
    public function test_to_database_handles_nested_records_recursively(): void
    {
        // Arrange: Create nested record structure with null values
        $manager = new TestUserRecord(
            id: 10,
            name: 'Manager Name',
            email: 'manager@example.com',
            status: TestBackedStringEnum::ACTIVE,
        );

        $record = new TestUserRecord(
            id: 6,
            name: 'Employee Name',
            email: 'employee@example.com',
            status: TestBackedStringEnum::ACTIVE,
            manager: $manager,
        );

        $record = new TestUserRecord(
            id: 6,
            name: 'Employee Name',
            email: 'employee@example.com',
            status: TestBackedStringEnum::ACTIVE,
            manager: $manager,
            createdAt: now()
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: Top-level null values are excluded
        $this->assertArrayNotHasKey('role', $result);
        $this->assertArrayNotHasKey('tags', $result);

        // Assert: Nested record is present
        $this->assertArrayHasKey('manager', $result);
        $this->assertIsArray($result['manager']);

        // Assert: status has a value, so it should be present
        $this->assertArrayHasKey('status', $result['manager']);
        $this->assertSame('active', $result['manager']['status']);

        // Assert: Null values in nested record are excluded
        $this->assertArrayNotHasKey('role', $result['manager']);
        $this->assertArrayNotHasKey('tags', $result['manager']);
        $this->assertArrayNotHasKey('manager', $result['manager']);

        // Assert: Non-null values are preserved
        $this->assertSame(10, $result['manager']['id']);
        $this->assertSame('Manager Name', $result['manager']['name']);
        $this->assertSame('manager@example.com', $result['manager']['email']);
    }

    /**
     * Test that toDatabase handles arrays with null values.
     *
     * Verifies that arrays are processed recursively and null elements are preserved.
     */
    public function test_to_database_removes_null_values_from_arrays(): void
    {
        // Arrange: Create record with array containing null values
        $record = new TestUserRecord(
            id: 7,
            name: 'Array Test',
            email: 'array@example.com',
            tags: [
                'first',
                null,
                'third',
                null,
                'fifth',
            ],
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: Null values in array should be removed
        $this->assertArrayHasKey('tags', $result);

        // Only non-null values should remain
        $this->assertCount(3, $result['tags']);

        // Keys are preserved (0, 2, 4) but we can use array_values for simple assertion
        $tagsArray = array_values($result['tags']);
        $this->assertSame('first', $tagsArray[0]);
        $this->assertSame('third', $tagsArray[1]);
        $this->assertSame('fifth', $tagsArray[2]);

        // Verify null values are gone
        $this->assertNotContains(null, $result['tags']);
    }

    /**
     * Test that toDatabase and toArray return different results when nulls present.
     *
     * Verifies that toDatabase excludes nulls while toArray keeps them.
     */
    public function test_to_database_and_to_array_differ_when_nulls_present(): void
    {
        // Arrange: Create record with null values
        $record = new TestUserRecord(
            id: 9,
            name: 'Comparison Test',
            email: 'compare@example.com',
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act
        $array = $record->toArray();
        $database = $record->toDatabase();

        // Assert: toArray has null fields, toDatabase does not
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertArrayHasKey('manager', $array);
        $this->assertNull($array['created_at']);
        $this->assertNull($array['role']);
        $this->assertNull($array['tags']);
        $this->assertNull($array['manager']);

        $this->assertArrayNotHasKey('created_at', $database);
        $this->assertArrayNotHasKey('role', $database);
        $this->assertArrayNotHasKey('tags', $database);
        $this->assertArrayNotHasKey('manager', $database);

        // Assert: Both have non-null fields
        $this->assertArrayHasKey('id', $database);
        $this->assertArrayHasKey('name', $database);
        $this->assertArrayHasKey('email', $database);
        $this->assertArrayHasKey('status', $database);
    }

    /**
     * Test that toDatabase is idempotent.
     *
     * Verifies that calling toDatabase multiple times produces identical results.
     */
    public function test_to_database_is_idempotent(): void
    {
        // Arrange: Create record with mixed null and non-null values
        $date = new DateTime('2024-03-20 12:00:00', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 11,
            name: 'Idempotent Test',
            email: 'idempotent@example.com',
            createdAt: $date,
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act: Call toDatabase twice
        $firstCall = $record->toDatabase();
        $secondCall = $record->toDatabase();

        // Assert: Both calls produce identical results
        $this->assertSame($firstCall, $secondCall);
    }

    /**
     * Test that toJson returns valid JSON string.
     *
     * Verifies that the toJson method produces a valid JSON representation
     * that matches the array structure.
     */
    public function test_to_json_returns_valid_json_string(): void
    {
        // Arrange: Create a complete record with all field types
        $date = new DateTime('2024-06-15 08:30:00', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 8,
            name: 'JSON Test',
            email: 'json@example.com',
            createdAt: $date,
            status: TestBackedStringEnum::INACTIVE,
            role: TestPureEnum::USER,
            tags: ['tag1', 'tag2'],
        );

        // Act: Convert to JSON
        $json = $record->toJson();
        $decoded = json_decode($json, true);

        // Assert: Verify JSON is valid
        $this->assertIsString($json);
        $this->assertNotNull($decoded);
        $this->assertJson($json);

        // Assert: Verify JSON content matches expected structure
        $this->assertSame(8, $decoded['id']);
        $this->assertSame('JSON Test', $decoded['name']);
        $this->assertSame('json@example.com', $decoded['email']);
        $this->assertSame('2024-06-15T08:30:00Z', $decoded['created_at']);
        $this->assertSame('inactive', $decoded['status']);
        $this->assertSame('USER', $decoded['role']);
        $this->assertSame(['tag1', 'tag2'], $decoded['tags']);
    }

    /**
     * Test that toArray handles empty array correctly.
     *
     * Verifies that empty arrays are preserved as empty arrays in the result.
     */
    public function test_to_array_handles_empty_array_correctly(): void
    {
        // Arrange: Create record with empty tags array
        $record = new TestUserRecord(
            id: 9,
            name: 'Empty Array Test',
            email: 'empty@example.com',
            tags: [],
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify empty array is preserved as empty array
        $this->assertIsArray($result['tags']);
        $this->assertEmpty($result['tags']);
    }

    /**
     * Test that record with multiple enums of both types works correctly.
     *
     * Verifies that a record containing both backed and pure enums
     * normalizes both types correctly.
     */
    public function test_to_array_handles_multiple_enums_correctly(): void
    {
        // Arrange: Create record with both enum types
        $record = new TestUserRecord(
            id: 10,
            name: 'Enum Test',
            email: 'enum@example.com',
            status: TestBackedStringEnum::PENDING,
            role: TestPureEnum::GUEST,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify both enums are normalized correctly
        $this->assertSame('pending', $result['status']);
        $this->assertSame('GUEST', $result['role']);
    }

    /**
     * Test that record instance implements Recordable interface.
     *
     * Verifies that TestUserRecord (through AbstractRecord) properly
     * implements the Recordable contract with toArray and toJson methods.
     */
    public function test_record_implements_recordable_interface(): void
    {
        // Arrange & Act
        $record = new TestUserRecord(
            id: 1,
            name: 'Interface Test',
            email: 'interface@example.com',
        );

        // Assert: Verify record is instance of AbstractRecord and has required methods
        $this->assertInstanceOf(AbstractRecord::class, $record);
        $this->assertTrue(method_exists($record, 'toArray'));
        $this->assertTrue(method_exists($record, 'toJson'));
        $this->assertIsArray($record->toArray());
        $this->assertIsString($record->toJson());
    }

    /**
     * Test that multiple toArray calls produce consistent results.
     *
     * Verifies that calling toArray multiple times on the same record
     * produces identical results (idempotent operation).
     */
    public function test_to_array_is_idempotent(): void
    {
        // Arrange: Create a record
        $date = new DateTime('2024-03-20 12:00:00', new \DateTimeZone('UTC'));
        $record = new TestUserRecord(
            id: 11,
            name: 'Idempotent Test',
            email: 'idempotent@example.com',
            createdAt: $date,
            status: TestBackedStringEnum::ACTIVE,
        );

        // Act: Call toArray twice
        $firstCall = $record->toArray();
        $secondCall = $record->toArray();

        // Assert: Verify both calls produce identical results
        $this->assertSame($firstCall, $secondCall);
    }
}
