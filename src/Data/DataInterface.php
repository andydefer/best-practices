<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Data;

/**
 * Contract for Data DTO objects.
 *
 * Defines the essential methods that all Data DTOs must implement to ensure
 * consistent data transformation across the application. Data DTOs are pure,
 * immutable structures used exclusively for HTTP responses.
 *
 * @author Andy Defer
 */
interface DataInterface
{
    /**
     * Converts the Data DTO to an associative array.
     *
     * The conversion handles:
     * - Nested Data objects (recursive conversion)
     * - Enums (converted to their scalar values or names)
     * - DateTime objects (converted to ISO 8601 format)
     * - Laravel Collections (converted to arrays)
     * - Property keys remain in camelCase
     *
     * @return array<string, mixed> Associative array representation of the DTO
     */
    public function toArray(): array;

    /**
     * Creates an array of Data DTO instances from an iterable source.
     *
     * Accepts either arrays or objects as source items. For objects, extracts
     * public properties to match the DTO constructor parameters.
     *
     * @param  iterable<object|array>  $items  Source items to convert
     * @return array<int, static> Array of DTO instances
     *
     * @throws InvalidArgumentException When an item is neither an object nor an array
     */
    public static function collect(iterable $items): array;
}
