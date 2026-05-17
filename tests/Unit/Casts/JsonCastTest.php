<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Casts;

use AndyDefer\BestPractices\Casts\JsonCast;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JsonCast.
 *
 * Verifies the casting behavior between JSON strings and PHP arrays
 * without database interaction, as these are pure transformations.
 */
#[AllowMockObjectsWithoutExpectations]
final class JsonCastTest extends TestCase
{
    private JsonCast $cast;

    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new JsonCast;
        $this->model = $this->createMock(Model::class);
    }

    /**
     * Verifies that null database values return null in the application.
     */
    public function test_get_returns_null_when_value_is_null(): void
    {
        // Arrange - Create cast instance with a null database value
        // Act - Perform the get operation with null input
        $result = $this->cast->get($this->model, 'metadata', null, []);

        // Assert - Null should remain null through the cast
        $this->assertNull($result);
    }

    /**
     * Verifies that existing arrays pass through unchanged.
     */
    public function test_get_returns_array_when_value_is_already_array(): void
    {
        // Arrange - Create an array that would come from an already cast value
        $expectedArray = ['key' => 'value', 'nested' => ['data' => 42]];

        // Act - Pass the array directly to get (simulating already cast data)
        $result = $this->cast->get($this->model, 'metadata', $expectedArray, []);

        // Assert - The array should be returned unchanged
        $this->assertSame($expectedArray, $result);
    }

    /**
     * Verifies that valid JSON strings are properly decoded to arrays.
     */
    public function test_get_decodes_valid_json_string_to_array(): void
    {
        // Arrange - Create a valid JSON string representing complex data
        $jsonString = '{"user":"John","preferences":{"theme":"dark","notifications":true}}';
        $expectedArray = ['user' => 'John', 'preferences' => ['theme' => 'dark', 'notifications' => true]];

        // Act - Decode the JSON string through the cast
        $result = $this->cast->get($this->model, 'metadata', $jsonString, []);

        // Assert - The result should match the expected PHP array structure
        $this->assertIsArray($result);
        $this->assertSame($expectedArray, $result);
    }

    /**
     * Verifies that empty JSON objects decode to empty arrays.
     */
    public function test_get_decodes_empty_json_object_to_empty_array(): void
    {
        // Arrange - Create an empty JSON object string
        $jsonString = '{}';

        // Act - Decode through the cast
        $result = $this->cast->get($this->model, 'metadata', $jsonString, []);

        // Assert - Empty JSON should become an empty array (consistent behavior)
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    /**
     * Verifies that invalid JSON gracefully returns null instead of throwing.
     */
    public function test_get_returns_null_when_json_string_is_invalid(): void
    {
        // Arrange - Create a malformed JSON string (missing quotes, invalid syntax)
        $invalidJson = '{invalid json with no quotes}';

        // Act - Attempt to decode invalid JSON through the cast
        $result = $this->cast->get($this->model, 'metadata', $invalidJson, []);

        // Assert - Should return null gracefully, not throw an exception
        $this->assertNull($result);
    }

    /**
     * Verifies that JSON with correct syntax but wrong type returns empty array.
     */
    public function test_get_returns_empty_array_when_json_decodes_to_non_array(): void
    {
        // Arrange - Valid JSON that decodes to a scalar (string) not an array
        $jsonString = '"just a string, not an array"';

        // Act - Decode to what should be an array context
        $result = $this->cast->get($this->model, 'metadata', $jsonString, []);

        // Assert - Should return empty array instead of the string
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    /**
     * Verifies that null values are properly stored as null in the database.
     */
    public function test_set_returns_null_when_value_is_null(): void
    {
        // Arrange - Prepare for set operation with null input
        // Act - Pass null to the set method
        $result = $this->cast->set($this->model, 'metadata', null, []);

        // Assert - Null should be returned for database storage
        $this->assertNull($result);
    }

    /**
     * Verifies that valid JSON strings pass through unchanged during set.
     */
    public function test_set_returns_string_when_value_is_valid_json_string(): void
    {
        // Arrange - Create a valid JSON string that should be stored as-is
        $validJsonString = '{"preserve":"me","keep":"unchanged"}';

        // Act - Pass the JSON string to set for storage
        $result = $this->cast->set($this->model, 'metadata', $validJsonString, []);

        // Assert - Valid JSON strings should be stored unchanged
        $this->assertSame($validJsonString, $result);
    }

    /**
     * Verifies that arrays are properly encoded to JSON for storage.
     */
    public function test_set_converts_array_to_json_string(): void
    {
        // Arrange - Create a PHP array to be stored in the database
        $inputArray = ['user' => 'Jane', 'roles' => ['admin', 'editor']];
        $expectedJson = json_encode($inputArray);

        // Act - Convert the array to a storage format
        $result = $this->cast->set($this->model, 'metadata', $inputArray, []);

        // Assert - Should return a JSON string matching the encoded array
        $this->assertIsString($result);
        $this->assertSame($expectedJson, $result);
    }

    /**
     * Verifies that encoding errors properly throw JsonException.
     */
    public function test_set_throws_json_exception_for_non_encodable_values(): void
    {
        // Arrange - Create a resource which cannot be encoded to JSON
        $nonEncodableResource = fopen('php://memory', 'r');

        // Act & Assert - Expect JsonException when trying to encode a resource
        $this->expectException(JsonException::class);
        $this->cast->set($this->model, 'metadata', $nonEncodableResource, []);

        // Clean up - Close the resource to prevent memory leaks
        fclose($nonEncodableResource);
    }
}
