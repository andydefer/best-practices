<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Data;

use Illuminate\Support\Collection as LaravelCollection;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

/**
 * Abstract base class for all Data DTOs.
 *
 * Provides pure data transformation capabilities including array conversion,
 * nested object handling, enum conversion, and date formatting. Data DTOs are
 * immutable structures used exclusively for API responses.
 *
 * @author Andy Defer
 */
abstract class AbstractData implements DataInterface
{
    /**
     * Converts the Data DTO to an associative array.
     *
     * Recursively processes all public properties, converting nested Data objects,
     * enums, collections, and date objects to their array/string representations.
     *
     * @return array<string, mixed> Associative array representation of the DTO
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);
            $key = $property->getName();

            $result[$key] = $this->transformValue($value);
        }

        return $result;
    }

    /**
     * Creates an array of Data DTO instances from an iterable source.
     *
     * @param  iterable<object|array>  $items  Source items to convert
     * @return array<int, static> Array of DTO instances
     *
     * @throws InvalidArgumentException When an item is neither an object nor an array
     */
    public static function collect(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = new static(...$item);
            } elseif (is_object($item)) {
                $result[] = new static(...self::extractPublicProperties($item));
            } else {
                throw new InvalidArgumentException(
                    sprintf('Item must be an object or array, %s given', gettype($item))
                );
            }
        }

        return $result;
    }

    /**
     * Recursively transforms a value for array representation.
     *
     * Handles:
     * - Null values (passed through)
     * - Enums (converted to scalar values or names)
     * - Arrays (recursively processed)
     * - Laravel Collections (converted to arrays recursively)
     * - Nested Data objects (converted via their toArray method)
     * - DateTime objects (formatted as ISO 8601)
     *
     * @param  mixed  $value  The value to transform
     * @return mixed Transformed value ready for array output
     */
    private function transformValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $this->transformEnum($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->transformValue($item), $value);
        }

        if ($value instanceof LaravelCollection) {
            return $value->map(fn ($item) => $this->transformValue($item))->toArray();
        }

        if ($value instanceof AbstractData) {
            return $value->toArray();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return $value;
    }

    /**
     * Converts an Enum to its scalar representation.
     *
     * For backed enums, returns the backing value (string|int).
     * For pure enums, returns the enum case name.
     *
     * @param  UnitEnum  $enum  The enum instance to convert
     * @return string|int Scalar representation of the enum
     */
    private function transformEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }

    /**
     * Extracts public properties from an object as an associative array.
     *
     * Used internally by the collect method to convert objects to arrays
     * before instantiating Data DTOs.
     *
     * @param  object  $object  The source object
     * @return array<string, mixed> Associative array of public property values
     */
    private static function extractPublicProperties(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $result[$property->getName()] = $property->getValue($object);
        }

        return $result;
    }
}
