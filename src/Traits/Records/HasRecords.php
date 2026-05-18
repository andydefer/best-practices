<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits;

use DateTimeInterface;
use Traversable;
use UnitEnum;

/**
 * Trait for converting records and collections to arrays.
 */
trait HasRecords
{
    /**
     * Recursively normalizes a value for serialization.
     *
     * @param  mixed  $value  The value to normalize
     * @return mixed Normalized value ready for array/JSON output
     */
    protected function normalizeValue(mixed $value): mixed
    {
        // Record object → recursively convert to array
        if ($value instanceof self && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        // Traversable → convert to array recursively
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
     * Recursively normalizes an array.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    protected function normalizeArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Converts a Traversable object to a normalized array.
     *
     * @param  Traversable  $traversable
     * @return array<int|string, mixed>
     */
    protected function normalizeTraversable(Traversable $traversable): array
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
     * @param  UnitEnum  $enum
     * @return string|int
     */
    protected function normalizeEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return strtolower($enum->name);
    }
}
