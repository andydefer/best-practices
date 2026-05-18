<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits\Enum;

/**
 * Provides common utility methods for PHP 8.1+ Enums.
 *
 * This trait adds convenient methods to enums for value validation, listing,
 * and case retrieval. It works with both backed enums (with scalar values)
 * and pure enums (without values).
 *
 * @author Andy Defer
 */
trait Enumable
{
    /**
     * Returns all scalar values from the enum.
     *
     * For backed enums (string|int), returns the backing values.
     * For pure enums (without values), returns the case names.
     *
     * @return array<int, string|int> Array of enum values or case names
     */
    public static function values(): array
    {
        if (self::isBackedEnum()) {
            return array_column(self::cases(), 'value');
        }

        return array_column(self::cases(), 'name');
    }

    /**
     * Returns all case names from the enum.
     *
     * @return array<int, string> Array of enum case names (UPPER_CASE format)
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Returns all enum cases in their defined order.
     *
     * This is an alias for the native cases() method that provides a more
     * semantic name when the intent is to respect the definition order.
     *
     * @return array<int, self> Array of all enum cases
     */
    public static function typesInOrder(): array
    {
        return self::cases();
    }

    /**
     * Checks if a given value exists in the enum.
     *
     * For backed enums, checks against backing values.
     * For pure enums, checks against case names.
     *
     * @param  string|int  $value  The value to validate
     * @return bool True if the value exists in the enum, false otherwise
     */
    public static function isValid(string|int $value): bool
    {
        if (self::isBackedEnum()) {
            return in_array($value, self::values(), true);
        }

        return in_array($value, self::names(), true);
    }

    /**
     * Retrieves the enum case corresponding to a value.
     *
     * For backed enums, returns the case with the matching backing value.
     * For pure enums, attempts to find a case by name (case-sensitive).
     *
     * @param  string|int  $value  The value to search for
     * @return self|null The matching enum case, or null if not found
     */
    public static function fromValue(string|int $value): ?self
    {
        if (self::isBackedEnum()) {
            return self::tryFrom($value);
        }

        $value = (string) $value;
        foreach (self::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Checks if the enum is a backed enum (has scalar values).
     *
     * @return bool True if the enum is backed, false if it's a pure enum
     */
    private static function isBackedEnum(): bool
    {
        return is_subclass_of(self::class, \BackedEnum::class);
    }
}
