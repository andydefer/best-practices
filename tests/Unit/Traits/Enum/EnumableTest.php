<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Traits\Enum;

use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\BestPractices\Tests\TestCase;

final class EnumableTest extends TestCase
{
    public function test_values_returns_backing_values_for_backed_string_enum(): void
    {
        // Arrange: Use a backed string enum fixture
        // Act: Call the values method on the enum
        $values = TestUserRole::values();

        // Assert: Verify all backing string values are returned correctly
        $this->assertSame(['admin', 'user', 'guest'], $values);
    }

    public function test_values_returns_backing_values_for_backed_int_enum(): void
    {
        // Arrange: Use a backed int enum fixture
        // Act: Call the values method on the enum
        $values = TestUserGrade::values();

        // Assert: Verify all backing int values are returned correctly
        $this->assertSame([1, 2, 3, 4], $values);
    }

    public function test_values_returns_case_names_for_pure_enum(): void
    {
        // Arrange: Use a pure enum fixture (no backing values)
        // Act: Call the values method on the enum
        $values = TestUserStatus::values();

        // Assert: Verify case names are returned as values since there's no backing value
        $this->assertSame(['ACTIVE', 'INACTIVE', 'SUSPENDED'], $values);
    }

    public function test_names_returns_all_case_names_for_backed_string_enum(): void
    {
        // Arrange: Use a backed string enum fixture
        // Act: Call the names method on the enum
        $names = TestUserRole::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['ADMIN', 'USER', 'GUEST'], $names);
    }

    public function test_names_returns_all_case_names_for_backed_int_enum(): void
    {
        // Arrange: Use a backed int enum fixture
        // Act: Call the names method on the enum
        $names = TestUserGrade::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['BRONZE', 'SILVER', 'GOLD', 'PLATINUM'], $names);
    }

    public function test_names_returns_all_case_names_for_pure_enum(): void
    {
        // Arrange: Use a pure enum fixture
        // Act: Call the names method on the enum
        $names = TestUserStatus::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['ACTIVE', 'INACTIVE', 'SUSPENDED'], $names);
    }

    public function test_types_in_order_returns_all_cases_in_definition_order(): void
    {
        // Arrange: Use a backed string enum fixture with specific definition order
        // Act: Call the typesInOrder method on the enum
        $cases = TestUserRole::typesInOrder();

        // Assert: Verify cases are returned in the same order they were defined
        $this->assertCount(3, $cases);
        $this->assertSame(TestUserRole::ADMIN, $cases[0]);
        $this->assertSame(TestUserRole::USER, $cases[1]);
        $this->assertSame(TestUserRole::GUEST, $cases[2]);
    }

    public function test_is_valid_returns_true_for_existing_backed_string_value(): void
    {
        // Arrange: Define a valid backing string value that exists in the enum
        $validValue = 'admin';

        // Act: Call isValid with the existing value
        $result = TestUserRole::isValid($validValue);

        // Assert: Verify the method returns true for existing values
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_backed_string_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 'unknown';

        // Act: Call isValid with the non-existing value
        $result = TestUserRole::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing values
        $this->assertFalse($result);
    }

    public function test_is_valid_returns_true_for_existing_backed_int_value(): void
    {
        // Arrange: Define a valid backing int value that exists in the enum
        $validValue = 2;

        // Act: Call isValid with the existing value
        $result = TestUserGrade::isValid($validValue);

        // Assert: Verify the method returns true for existing values
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_backed_int_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 99;

        // Act: Call isValid with the non-existing value
        $result = TestUserGrade::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing values
        $this->assertFalse($result);
    }

    public function test_is_valid_returns_true_for_existing_pure_enum_case_name(): void
    {
        // Arrange: Define a valid case name from the pure enum
        $validValue = 'ACTIVE';

        // Act: Call isValid with the existing case name
        $result = TestUserStatus::isValid($validValue);

        // Assert: Verify the method returns true for existing case names
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_pure_enum_case_name(): void
    {
        // Arrange: Define an invalid case name that does not exist in the pure enum
        $invalidValue = 'SUPER_ADMIN';

        // Act: Call isValid with the non-existing case name
        $result = TestUserStatus::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing case names
        $this->assertFalse($result);
    }

    public function test_from_value_returns_enum_case_for_existing_backed_string_value(): void
    {
        // Arrange: Define a valid backing string value
        $validValue = 'admin';

        // Act: Call fromValue with the existing value
        $result = TestUserRole::fromValue($validValue);

        // Assert: Verify the correct enum case is returned
        $this->assertSame(TestUserRole::ADMIN, $result);
    }

    public function test_from_value_returns_null_for_non_existing_backed_string_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 'unknown';

        // Act: Call fromValue with the non-existing value
        $result = TestUserRole::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing values
        $this->assertNull($result);
    }

    public function test_from_value_returns_enum_case_for_existing_backed_int_value(): void
    {
        // Arrange: Define a valid backing int value
        $validValue = 2;

        // Act: Call fromValue with the existing value
        $result = TestUserGrade::fromValue($validValue);

        // Assert: Verify the correct enum case is returned
        $this->assertSame(TestUserGrade::SILVER, $result);
    }

    public function test_from_value_returns_null_for_non_existing_backed_int_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 99;

        // Act: Call fromValue with the non-existing value
        $result = TestUserGrade::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing values
        $this->assertNull($result);
    }

    public function test_from_value_returns_enum_case_for_existing_pure_enum_case_name(): void
    {
        // Arrange: Define a valid case name from the pure enum
        $validValue = 'ACTIVE';

        // Act: Call fromValue with the existing case name
        $result = TestUserStatus::fromValue($validValue);

        // Assert: Verify the correct enum case is returned for pure enums by name
        $this->assertSame(TestUserStatus::ACTIVE, $result);
    }

    public function test_from_value_returns_null_for_non_existing_pure_enum_case_name(): void
    {
        // Arrange: Define an invalid case name that does not exist in the pure enum
        $invalidValue = 'SUPER_ADMIN';

        // Act: Call fromValue with the non-existing case name
        $result = TestUserStatus::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing case names
        $this->assertNull($result);
    }
}
