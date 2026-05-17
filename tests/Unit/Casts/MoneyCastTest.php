<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Casts;

use AndyDefer\BestPractices\Casts\MoneyCast;
use AndyDefer\BestPractices\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Test suite for MoneyCast.
 *
 * Verifies the conversion between smallest currency units (database)
 * and standard currency units (application).
 * The cast stores monetary values as integers (cents, pence, etc.) in the database
 * and presents them as floats (dollars, euros, etc.) to the application.
 */
#[AllowMockObjectsWithoutExpectations]
final class MoneyCastTest extends TestCase
{
    private MoneyCast $cast;

    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new MoneyCast;
        $this->model = $this->createMock(Model::class);
    }

    /**
     * Test that get converts smallest currency units to standard units with two decimals.
     *
     * Verifies that 1234 cents (integer) is correctly transformed to 12.34 (float).
     */
    public function test_get_converts_cents_to_euros_with_two_decimals(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->get($model, 'amount', 1234, []);

        // Assert
        $this->assertIsFloat($result);
        $this->assertSame(12.34, $result);
    }

    /**
     * Test that get correctly rounds values during conversion.
     *
     * Verifies that edge cases like 123 becomes 1.23,
     * 5 becomes 0.05, and 0 becomes 0.00.
     */
    public function test_get_rounds_cents_correctly(): void
    {
        // Arrange
        $model = $this->model;

        // Act & Assert
        $result = $this->cast->get($model, 'amount', 123, []);
        $this->assertSame(1.23, $result);

        $result = $this->cast->get($model, 'amount', 5, []);
        $this->assertSame(0.05, $result);

        $result = $this->cast->get($model, 'amount', 0, []);
        $this->assertSame(0.00, $result);
    }

    /**
     * Test that get handles large monetary amounts without precision loss.
     *
     * Verifies that 123,456,789 becomes 1,234,567.89 correctly.
     */
    public function test_get_handles_large_amounts(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->get($model, 'amount', 123456789, []);

        // Assert
        $this->assertSame(1234567.89, $result);
    }

    /**
     * Test that get handles negative amounts correctly.
     *
     * Verifies that -500 becomes -5.00.
     */
    public function test_get_handles_negative_amounts(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->get($model, 'amount', -500, []);

        // Assert
        $this->assertSame(-5.00, $result);
    }

    /**
     * Test that get returns null when database value is null.
     *
     * CRITICAL: The cast must handle nullable database columns gracefully.
     * Without this, retrieving a model with a NULL price column would throw a TypeError.
     */
    public function test_get_returns_null_when_value_is_null(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->get($model, 'amount', null, []);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test that get handles nullable column with existing model.
     *
     * Ensures that when a column is nullable, the cast returns null appropriately.
     */
    public function test_get_handles_nullable_column_with_existing_model(): void
    {
        // Arrange
        $model = $this->model;

        // Simulate a real scenario where price column is NULL in database
        // Act
        $result = $this->cast->get($model, 'price', null, ['price' => null]);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test that set converts standard currency units to smallest units for storage.
     *
     * Verifies that 12.34 is correctly transformed to 1234 (integer).
     */
    public function test_set_converts_euros_to_cents(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', 12.34, []);

        // Assert
        $this->assertIsInt($result);
        $this->assertSame(1234, $result);
    }

    /**
     * Test that set rounds values correctly during conversion.
     *
     * Verifies that 1.234 becomes 123 (rounded down),
     * 0.055 becomes 6 (rounded up),
     * and 1.999 becomes 200 (rounded up).
     */
    public function test_set_rounds_cents_correctly(): void
    {
        // Arrange
        $model = $this->model;

        // Act & Assert
        $result = $this->cast->set($model, 'amount', 1.234, []);
        $this->assertSame(123, $result);

        $result = $this->cast->set($model, 'amount', 0.055, []);
        $this->assertSame(6, $result);

        $result = $this->cast->set($model, 'amount', 1.999, []);
        $this->assertSame(200, $result);
    }

    /**
     * Test that set handles large monetary amounts without precision loss.
     *
     * Verifies that 1,234,567.89 becomes 123,456,789.
     */
    public function test_set_handles_large_amounts(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', 1234567.89, []);

        // Assert
        $this->assertSame(123456789, $result);
    }

    /**
     * Test that set handles negative amounts correctly.
     *
     * Verifies that -5.00 becomes -500.
     */
    public function test_set_handles_negative_amounts(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', -5.00, []);

        // Assert
        $this->assertSame(-500, $result);
    }

    /**
     * Test that set handles integer input correctly.
     *
     * Verifies that 10 becomes 1000.
     */
    public function test_set_handles_integer_input(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', 10, []);

        // Assert
        $this->assertSame(1000, $result);
    }

    /**
     * Test that set handles zero values correctly.
     *
     * Verifies that 0 and 0.00 both become 0.
     */
    public function test_set_handles_zero(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', 0, []);
        $result2 = $this->cast->set($model, 'amount', 0.00, []);

        // Assert
        $this->assertSame(0, $result);
        $this->assertSame(0, $result2);
    }

    /**
     * Test that set returns null when application value is null.
     *
     * CRITICAL: The cast must allow setting null values for nullable columns.
     * Without this, setting a price to null would throw a TypeError on save.
     */
    public function test_set_returns_null_when_value_is_null(): void
    {
        // Arrange
        $model = $this->model;

        // Act
        $result = $this->cast->set($model, 'amount', null, []);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test that set allows clearing a value (setting to null).
     *
     * Verifies that business logic can explicitly set a monetary value to null.
     */
    public function test_set_allows_clearing_value(): void
    {
        // Arrange
        $model = $this->model;

        // Act - User wants to remove the price (set to NULL in DB)
        $result = $this->cast->set($model, 'price', null, []);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test that null values are preserved through round trip.
     *
     * Verifies that null values are preserved through the complete transformation
     * (application → DB → application).
     */
    public function test_null_values_are_preserved_through_round_trip(): void
    {
        // Arrange
        $model = $this->model;

        // Act - Simulate application → DB
        $dbValue = $this->cast->set($model, 'amount', null, []);

        // Simulate DB → application
        $appValue = $this->cast->get($model, 'amount', $dbValue, []);

        // Assert
        $this->assertNull($dbValue);
        $this->assertNull($appValue);
    }

    /**
     * Test that get and set handle null consistently with nullable types.
     *
     * Verifies the complete workflow when dealing with nullable monetary values.
     */
    public function test_complete_workflow_with_nullable_values(): void
    {
        // Arrange
        $model = $this->model;

        // Act - Start with null
        $nullInApp = null;

        // Set to DB
        $dbValue = $this->cast->set($model, 'amount', $nullInApp, []);

        // Get from DB
        $appValue = $this->cast->get($model, 'amount', $dbValue, []);

        // Assert
        $this->assertNull($dbValue);
        $this->assertNull($appValue);

        // Act - Set a real value then set back to null
        $dbValue = $this->cast->set($model, 'amount', 12.34, []);
        $this->assertSame(1234, $dbValue);

        $dbValue = $this->cast->set($model, 'amount', null, []);
        $this->assertNull($dbValue);
    }
}
