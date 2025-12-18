<?php

namespace CodyJHeiser\Db2Eloquent\Casts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for IBM date fields where special values (like 99999999) mean "no date".
 *
 * Extends IbmDate to handle:
 * - GET: Returns null for 99999999 or other invalid dates
 * - SET: Converts null to 99999999
 *
 * Usage:
 *   protected $casts = [
 *       'PBTODT' => IbmDateNullable::class,           // Returns Carbon or null
 *       'PBTODT' => IbmDateNullable::class.':date',   // Returns Y-m-d string or null
 *   ];
 */
class IbmDateNullable extends IbmDate
{
    /**
     * The value representing "no date" / "no expiration".
     */
    public const NO_DATE_VALUE = 99999999;

    /**
     * Cast the given value (from DB integer to Carbon or formatted string).
     * Returns null for NO_DATE_VALUE or invalid dates.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CarbonInterface|string|null
    {
        if ($value === null || $value === 0 || $value === '') {
            return null;
        }

        // Treat NO_DATE_VALUE as null
        if ((int) $value === self::NO_DATE_VALUE) {
            return null;
        }

        $dateString = (string) $value;

        if (strlen($dateString) !== 8) {
            return null;
        }

        // Validate date components
        $year = (int) substr($dateString, 0, 4);
        $month = (int) substr($dateString, 4, 2);
        $day = (int) substr($dateString, 6, 2);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return parent::get($model, $key, $value, $attributes);
    }

    /**
     * Prepare the given value for storage.
     * Converts null to NO_DATE_VALUE.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return self::NO_DATE_VALUE;
        }

        return parent::set($model, $key, $value, $attributes);
    }
}
