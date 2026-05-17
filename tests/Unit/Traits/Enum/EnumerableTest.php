<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Traits\Enum;

use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedIntEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestPureEnum;
use AndyDefer\BestPractices\Tests\TestCase;

final class EnumerableTest extends TestCase
{
    public function test_values_returns_backing_values_for_backed_string_enum(): void
    {
        // Arrange: Use a backed string enum fixture
        // Act: Call the values method on the enum
        $values = TestBackedStringEnum::values();

        // Assert: Verify all backing string values are returned correctly
        $this->assertSame(['active', 'inactive', 'pending'], $values);
    }

    public function test_values_returns_backing_values_for_backed_int_enum(): void
    {
        // Arrange: Use a backed int enum fixture
        // Act: Call the values method on the enum
        $values = TestBackedIntEnum::values();

        // Assert: Verify all backing int values are returned correctly
        $this->assertSame([1, 2, 3], $values);
    }

    public function test_values_returns_case_names_for_pure_enum(): void
    {
        // Arrange: Use a pure enum fixture (no backing values)
        // Act: Call the values method on the enum
        $values = TestPureEnum::values();

        // Assert: Verify case names are returned as values since there's no backing value
        $this->assertSame(['ADMIN', 'USER', 'GUEST'], $values);
    }

    public function test_names_returns_all_case_names_for_backed_string_enum(): void
    {
        // Arrange: Use a backed string enum fixture
        // Act: Call the names method on the enum
        $names = TestBackedStringEnum::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['ACTIVE', 'INACTIVE', 'PENDING'], $names);
    }

    public function test_names_returns_all_case_names_for_backed_int_enum(): void
    {
        // Arrange: Use a backed int enum fixture
        // Act: Call the names method on the enum
        $names = TestBackedIntEnum::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['ONE', 'TWO', 'THREE'], $names);
    }

    public function test_names_returns_all_case_names_for_pure_enum(): void
    {
        // Arrange: Use a pure enum fixture
        // Act: Call the names method on the enum
        $names = TestPureEnum::names();

        // Assert: Verify all case names are returned in UPPER_CASE format
        $this->assertSame(['ADMIN', 'USER', 'GUEST'], $names);
    }

    public function test_types_in_order_returns_all_cases_in_definition_order(): void
    {
        // Arrange: Use a backed string enum fixture with specific definition order
        // Act: Call the typesInOrder method on the enum
        $cases = TestBackedStringEnum::typesInOrder();

        // Assert: Verify cases are returned in the same order they were defined
        $this->assertCount(3, $cases);
        $this->assertSame(TestBackedStringEnum::ACTIVE, $cases[0]);
        $this->assertSame(TestBackedStringEnum::INACTIVE, $cases[1]);
        $this->assertSame(TestBackedStringEnum::PENDING, $cases[2]);
    }

    public function test_is_valid_returns_true_for_existing_backed_string_value(): void
    {
        // Arrange: Define a valid backing string value that exists in the enum
        $validValue = 'active';

        // Act: Call isValid with the existing value
        $result = TestBackedStringEnum::isValid($validValue);

        // Assert: Verify the method returns true for existing values
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_backed_string_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 'unknown';

        // Act: Call isValid with the non-existing value
        $result = TestBackedStringEnum::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing values
        $this->assertFalse($result);
    }

    public function test_is_valid_returns_true_for_existing_backed_int_value(): void
    {
        // Arrange: Define a valid backing int value that exists in the enum
        $validValue = 2;

        // Act: Call isValid with the existing value
        $result = TestBackedIntEnum::isValid($validValue);

        // Assert: Verify the method returns true for existing values
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_backed_int_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 99;

        // Act: Call isValid with the non-existing value
        $result = TestBackedIntEnum::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing values
        $this->assertFalse($result);
    }

    public function test_is_valid_returns_true_for_existing_pure_enum_case_name(): void
    {
        // Arrange: Define a valid case name from the pure enum
        $validValue = 'ADMIN';

        // Act: Call isValid with the existing case name
        $result = TestPureEnum::isValid($validValue);

        // Assert: Verify the method returns true for existing case names
        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_non_existing_pure_enum_case_name(): void
    {
        // Arrange: Define an invalid case name that does not exist in the pure enum
        $invalidValue = 'SUPER_ADMIN';

        // Act: Call isValid with the non-existing case name
        $result = TestPureEnum::isValid($invalidValue);

        // Assert: Verify the method returns false for non-existing case names
        $this->assertFalse($result);
    }

    public function test_from_value_returns_enum_case_for_existing_backed_string_value(): void
    {
        // Arrange: Define a valid backing string value
        $validValue = 'active';

        // Act: Call fromValue with the existing value
        $result = TestBackedStringEnum::fromValue($validValue);

        // Assert: Verify the correct enum case is returned
        $this->assertSame(TestBackedStringEnum::ACTIVE, $result);
    }

    public function test_from_value_returns_null_for_non_existing_backed_string_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 'unknown';

        // Act: Call fromValue with the non-existing value
        $result = TestBackedStringEnum::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing values
        $this->assertNull($result);
    }

    public function test_from_value_returns_enum_case_for_existing_backed_int_value(): void
    {
        // Arrange: Define a valid backing int value
        $validValue = 2;

        // Act: Call fromValue with the existing value
        $result = TestBackedIntEnum::fromValue($validValue);

        // Assert: Verify the correct enum case is returned
        $this->assertSame(TestBackedIntEnum::TWO, $result);
    }

    public function test_from_value_returns_null_for_non_existing_backed_int_value(): void
    {
        // Arrange: Define an invalid value that does not exist in the enum
        $invalidValue = 99;

        // Act: Call fromValue with the non-existing value
        $result = TestBackedIntEnum::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing values
        $this->assertNull($result);
    }

    public function test_from_value_returns_enum_case_for_existing_pure_enum_case_name(): void
    {
        // Arrange: Define a valid case name from the pure enum
        $validValue = 'ADMIN';

        // Act: Call fromValue with the existing case name
        $result = TestPureEnum::fromValue($validValue);

        // Assert: Verify the correct enum case is returned for pure enums by name
        $this->assertSame(TestPureEnum::ADMIN, $result);
    }

    public function test_from_value_returns_null_for_non_existing_pure_enum_case_name(): void
    {
        // Arrange: Define an invalid case name that does not exist in the pure enum
        $invalidValue = 'SUPER_ADMIN';

        // Act: Call fromValue with the non-existing case name
        $result = TestPureEnum::fromValue($invalidValue);

        // Assert: Verify null is returned for non-existing case names
        $this->assertNull($result);
    }
}
