<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use Traversable;
use UnitEnum;

/**
 * Abstract base class for all Record DTOs.
 *
 * Provides pure data transformation capabilities including array conversion,
 * snake_case key normalization, nested record handling, enum conversion,
 * and date formatting. Records are immutable structures used exclusively for
 * internal communication between Services and Repositories.
 *
 * @author Andy Defer
 */
abstract class AbstractRecord implements Recordable
{
    /**
     * Converts the Record to an associative array with snake_case keys.
     *
     * Recursively processes all public properties, converting nested Record objects,
     * traversable structures, enums, arrays, and date objects to their array/string
     * representations. All array keys are automatically converted from camelCase
     * to snake_case for database compatibility.
     *
     * @return array<string, mixed> Associative array representation of the Record
     */
    public function toArray(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();

        return $this->normalizeArray($properties);
    }

    /**
     * Converts the Record to an associative array for database operations.
     *
     * Only includes non-null values, making it ideal for update operations
     * where you only want to set provided fields. Keys are converted to snake_case.
     *
     * @return array<string, mixed> Associative array with only non-null values
     */
    public function toDatabase(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();
        $normalized = $this->normalizeArray($properties);

        // Remove null values recursively
        return $this->removeNullValues($normalized);
    }

    /**
     * Recursively removes null values from an array.
     *
     * @param  array<string, mixed>  $array  Array to clean
     * @return array<string, mixed> Array without null values
     */
    private function removeNullValues(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->removeNullValues($value);
                if (empty($value)) {
                    continue;
                }
                $result[$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Converts the Record to a JSON string.
     *
     * @return string JSON representation of the Record
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Extracts all public properties with keys converted to snake_case.
     *
     * Uses reflection to access all public properties of the record,
     * converts property names from camelCase to snake_case, and preserves
     * the original values for further normalization.
     *
     * @return array<string, mixed> Associative array with snake_case keys
     */
    private function extractPublicPropertiesWithSnakeKeys(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);
            $key = $this->convertCamelToSnake($property->getName());
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Converts a camelCase string to snake_case.
     *
     * @param  string  $input  CamelCase string to convert
     * @return string snake_case representation
     */
    private function convertCamelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Recursively normalizes array values for serialization.
     *
     * @param  array<string, mixed>  $array  Array to normalize
     * @return array<string, mixed> Normalized array
     */
    private function normalizeArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Recursively normalizes a single value for serialization.
     *
     * Handles:
     * - Nested Record objects (converted via their toArray method)
     * - Traversable objects (converted to arrays recursively)
     * - Enums (converted to scalar values or names)
     * - DateTime objects (formatted as ISO 8601 UTC)
     * - Arrays (recursively processed)
     * - Null values (passed through)
     * - Other scalars (passed through unchanged)
     *
     * @param  mixed  $value  The value to normalize
     * @return mixed Normalized value ready for array/JSON output
     */
    private function normalizeValue(mixed $value): mixed
    {
        // Record object → recursively convert to array
        if ($value instanceof self) {
            return $value->toArray();
        }

        // Traversable (Collection, ArrayIterator, etc.) → convert to array recursively
        if ($value instanceof Traversable) {
            return $this->normalizeTraversable($value);
        }

        // Enum → convert to scalar value (backed) or case name (pure)
        if ($value instanceof UnitEnum) {
            return $this->normalizeEnum($value);
        }

        // DateTimeInterface → convert to UTC ISO 8601 string
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        // Array → recursively normalize each element
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        // Null or scalar → return as-is
        return $value;
    }

    /**
     * Converts a Traversable object to a normalized array.
     *
     * Recursively processes each element of the traversable structure,
     * applying the same normalization rules to nested values.
     *
     * @param  Traversable  $traversable  The traversable object to convert
     * @return array<int|string, mixed> Normalized array representation
     */
    private function normalizeTraversable(Traversable $traversable): array
    {
        $result = [];

        foreach ($traversable as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Converts an Enum to its serializable representation.
     *
     * For backed enums (string/int backed), returns the backing value.
     * For pure enums (non-backed), returns the enum case name.
     *
     * @param  UnitEnum  $enum  The enum instance to convert
     * @return string|int Scalar representation of the enum
     */
    private function normalizeEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }
}
