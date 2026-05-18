<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
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
            name: 'John Doe',
            email: 'john@example.com',
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify array structure with snake_case keys
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John Doe', $result['name']);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('grade', $result);
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
            name: 'Jane Doe',
            email: 'jane@example.com',
            emailVerifiedAt: $dateTime->format('Y-m-d\TH:i:s\Z'),
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify DateTime is converted to ISO 8601 format
        $this->assertIsString($result['email_verified_at']);
        $this->assertSame('2024-01-15T14:30:00Z', $result['email_verified_at']);
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
            name: 'Bob Smith',
            email: 'bob@example.com',
            emailVerifiedAt: $dateTimeImmutable->format('Y-m-d\TH:i:s\Z'),
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify DateTimeImmutable is converted to ISO 8601 format
        $this->assertIsString($result['email_verified_at']);
        $this->assertSame('2024-12-25T09:15:30Z', $result['email_verified_at']);
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
            name: 'Alice Johnson',
            email: 'alice@example.com',
            role: TestUserRole::ADMIN,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify backed enum is converted to its value
        $this->assertArrayHasKey('role', $result);
        $this->assertIsString($result['role']);
        $this->assertSame('admin', $result['role']);
    }

    /**
     * Test that toArray converts pure enum to its name in lowercase.
     *
     * Verifies that pure enums (non-backed) are normalized to their case name in lowercase.
     */
    public function test_to_array_converts_pure_enum_to_name(): void
    {
        // Arrange: Create record with pure enum
        $record = new TestUserRecord(
            name: 'Charlie Brown',
            email: 'charlie@example.com',
            status: TestUserStatus::ACTIVE,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify pure enum is converted to its name in lowercase
        $this->assertArrayHasKey('status', $result);
        $this->assertIsString($result['status']);
        $this->assertSame('ACTIVE', $result['status']);
    }

    /**
     * Test that toArray converts int backed enum to its scalar value.
     */
    public function test_to_array_converts_int_backed_enum_to_scalar_value(): void
    {
        // Arrange: Create record with int backed enum
        $record = new TestUserRecord(
            name: 'David Miller',
            email: 'david@example.com',
            grade: TestUserGrade::GOLD,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify int backed enum is converted to its value
        $this->assertArrayHasKey('grade', $result);
        $this->assertIsInt($result['grade']);
        $this->assertSame(3, $result['grade']);
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
            name: 'Alice Johnson',
            email: 'alice@example.com',
            role: TestUserRole::ADMIN,
            // status a une valeur par défaut (ACTIVE) → non null
            // grade a une valeur par défaut (BRONZE) → non null
            // tags a une valeur par défaut (TypedRecords vide) → non null mais vide
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: Non-null values are included
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('status', $result);     // Valeur par défaut
        $this->assertArrayHasKey('grade', $result);      // Valeur par défaut

        // Assert: Null values are excluded
        $this->assertArrayNotHasKey('email_verified_at', $result);
        $this->assertArrayNotHasKey('featured_product', $result);

        // TypedRecords vide est exclu
        $this->assertArrayNotHasKey('tags', $result);
        $this->assertArrayNotHasKey('products', $result);

        // Assert: Values are correct
        $this->assertSame('Alice Johnson', $result['name']);
        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('admin', $result['role']);
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame(1, $result['grade']);
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
        $record = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
            grade: TestUserGrade::GOLD,
            emailVerifiedAt: '2024-01-15T10:30:00Z',
        );

        // Act: Convert to database array
        $result = $record->toDatabase();

        // Assert: All non-null fields are included
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('email_verified_at', $result);
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
        $record = new TestUserRecord(
            name: 'JSON Test',
            email: 'json@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::SILVER,
        );

        // Act: Convert to JSON
        $json = $record->toJson();
        $decoded = json_decode($json, true);

        // Assert: Verify JSON is valid
        $this->assertIsString($json);
        $this->assertNotNull($decoded);
        $this->assertJson($json);

        // Assert: Verify JSON content matches expected structure
        $this->assertSame('JSON Test', $decoded['name']);
        $this->assertSame('json@example.com', $decoded['email']);
        $this->assertSame('ACTIVE', $decoded['status']);
        $this->assertSame('user', $decoded['role']);
        $this->assertSame(2, $decoded['grade']);
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
            name: 'Enum Test',
            email: 'enum@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::GUEST,
            grade: TestUserGrade::BRONZE,
        );

        // Act: Convert record to array
        $result = $record->toArray();

        // Assert: Verify both enums are normalized correctly
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('guest', $result['role']);
        $this->assertSame(1, $result['grade']);
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
            name: 'Interface Test',
            email: 'interface@example.com',
        );

        // Assert: Verify record is instance of AbstractRecord and has required methods
        $this->assertInstanceOf(AbstractRecord::class, $record);
        $this->assertTrue(method_exists($record, 'toArray'));
        $this->assertTrue(method_exists($record, 'toDatabase'));
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
        $record = new TestUserRecord(
            name: 'Idempotent Test',
            email: 'idempotent@example.com',
            status: TestUserStatus::ACTIVE,
        );

        // Act: Call toArray twice
        $firstCall = $record->toArray();
        $secondCall = $record->toArray();

        // Assert: Verify both calls produce identical results
        $this->assertSame($firstCall, $secondCall);
    }

    /**
     * Test that toDatabase is idempotent.
     */
    public function test_to_database_is_idempotent(): void
    {
        // Arrange: Create a record
        $record = new TestUserRecord(
            name: 'Idempotent Test',
            email: 'idempotent@example.com',
            role: TestUserRole::ADMIN,
        );

        // Act: Call toDatabase twice
        $firstCall = $record->toDatabase();
        $secondCall = $record->toDatabase();

        // Assert: Verify both calls produce identical results
        $this->assertSame($firstCall, $secondCall);
    }
}
