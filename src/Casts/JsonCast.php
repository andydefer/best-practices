<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * Handles casting between JSON database storage and PHP arrays.
 *
 * This cast ensures safe conversion between JSON strings stored in the database
 * and PHP arrays used in the application. It gracefully handles invalid JSON
 * by returning null, preventing application crashes from corrupted data.
 */
final class JsonCast implements CastsAttributes
{
    /**
     * Maximum JSON nesting depth for decoding operations.
     *
     * @var int
     */
    private const MAX_JSON_DEPTH = 512;

    /**
     * Converts JSON from database storage to a PHP array.
     *
     * Handles various input types and gracefully degrades to null for invalid JSON.
     * This prevents application crashes when encountering corrupted database data.
     *
     * @param  Model  $model  The Eloquent model being cast
     * @param  string  $key  The attribute name being cast
     * @param  string|array|null  $value  The raw value from the database
     * @param  array<string, mixed>  $attributes  All model attributes
     * @return array|null The decoded array, or null if conversion fails
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->decodeJsonString($value);
        }

        return null;
    }

    /**
     * Converts a PHP array to JSON for database storage.
     *
     * Handles arrays, existing JSON strings, and gracefully falls back to
     * JSON encoding with strict error handling.
     *
     * @param  Model  $model  The Eloquent model being cast
     * @param  string  $key  The attribute name being cast
     * @param  array|string|null  $value  The application value to store
     * @param  array<string, mixed>  $attributes  All model attributes
     * @return string|null The JSON string for storage, or null if input is null
     *
     * @throws JsonException When encoding fails for non-null, non-string, non-array values
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && $this->isValidJsonString($value)) {
            return $value;
        }

        return $this->encodeToJson($value);
    }

    /**
     * Decodes a JSON string to a PHP array.
     *
     * @param  string  $jsonString  The JSON string to decode
     * @return array|null The decoded array, or null if decoding fails
     */
    private function decodeJsonString(string $jsonString): ?array
    {
        try {
            $decoded = json_decode(
                json: $jsonString,
                associative: true,
                depth: self::MAX_JSON_DEPTH,
                flags: JSON_THROW_ON_ERROR
            );

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Encodes a value to a JSON string.
     *
     * @param  mixed  $value  The value to encode
     * @return string The JSON encoded string
     *
     * @throws JsonException When encoding fails
     */
    private function encodeToJson(mixed $value): string
    {
        return json_encode(
            value: $value,
            flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Validates if a string contains valid JSON.
     *
     * @param  string  $value  The string to validate
     * @return bool True if the string contains valid JSON, false otherwise
     */
    private function isValidJsonString(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
