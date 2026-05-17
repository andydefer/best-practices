<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles casting between monetary values stored as integers (smallest currency unit)
 * in database and floats (standard currency unit) in application.
 *
 * This cast ensures precise monetary value storage by storing amounts as integers
 * (cents, pence, etc.) in the database, preventing floating-point precision errors.
 * Values are presented as floats to the application with proper rounding to 2 decimals.
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * Number of decimal places for monetary values (standard currency precision).
     *
     * @var int
     */
    private const DECIMAL_PLACES = 2;

    /**
     * Conversion multiplier between standard currency unit and smallest unit.
     * Calculated as 10^DECIMAL_PLACES (100 for 2 decimal places).
     *
     * @var int
     */
    private const UNIT_MULTIPLIER = 100;

    /**
     * Converts from smallest currency unit (database) to standard unit (application).
     *
     * Handles nullable values gracefully and ensures proper rounding to 2 decimal places.
     * Example: 1234 cents → 12.34 dollars/euros
     *
     * @param  Model  $model  The Eloquent model being cast
     * @param  string  $key  The attribute name being cast
     * @param  int|null  $value  The value in smallest currency unit from database
     * @param  array<string, mixed>  $attributes  All model attributes
     * @return float|null The value in standard currency unit for application
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }

        return round(
            num: (int) $value / self::UNIT_MULTIPLIER,
            precision: self::DECIMAL_PLACES
        );
    }

    /**
     * Converts from standard currency unit (application) to smallest unit (database).
     *
     * Handles nullable values gracefully and ensures proper rounding before conversion.
     * Example: 12.34 dollars/euros → 1234 cents
     *
     * @param  Model  $model  The Eloquent model being cast
     * @param  string  $key  The attribute name being cast
     * @param  float|int|null  $value  The value in standard currency unit from application
     * @param  array<string, mixed>  $attributes  All model attributes
     * @return int|null The value in smallest currency unit for database storage
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) round(
            num: (float) $value * self::UNIT_MULTIPLIER,
            precision: 0
        );
    }
}
