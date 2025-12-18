<?php

namespace CodyJHeiser\Db2Eloquent\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cast for IBM date fields stored as integers in Ymd format (e.g., 20251217).
 *
 * Usage in model:
 *   protected $casts = [
 *       'SHCLDT' => IbmDate::class,                    // Returns Carbon instance
 *       'SHCLDT' => IbmDate::class.':Y-m-d',           // Returns formatted string
 *       'SHCLDT' => IbmDate::class.':date',            // Shorthand for Y-m-d
 *       'SHCLDT' => IbmDate::class.':m/d/Y',           // Custom format
 *   ];
 *
 * Returns Carbon instance or formatted string when getting.
 * Accepts Carbon/string/int when setting.
 */
class IbmDate implements CastsAttributes
{
    /**
     * The timezone to use for dates.
     */
    protected string $timezone = 'America/Chicago';

    /**
     * The output format (null returns Carbon instance).
     */
    protected ?string $format = null;

    /**
     * Format shortcuts.
     */
    protected array $formatShortcuts = [
        'date' => 'Y-m-d',
        'us' => 'm/d/Y',
        'eu' => 'd/m/Y',
    ];

    /**
     * Create a new cast instance.
     *
     * @param string|null $format Optional output format (e.g., 'Y-m-d', 'date', 'm/d/Y')
     */
    public function __construct(?string $format = null)
    {
        if ($format !== null) {
            // Check for shortcut formats
            $this->format = $this->formatShortcuts[$format] ?? $format;
        }
    }

    /**
     * Cast the given value (from DB integer to Carbon or formatted string).
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return CarbonInterface|string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CarbonInterface|string|null
    {
        if ($value === null || $value === 0 || $value === '') {
            return null;
        }

        // Convert integer 20251217 to string and parse
        $dateString = (string) $value;

        if (strlen($dateString) !== 8) {
            return null;
        }

        $carbon = Carbon::createFromFormat('Ymd', $dateString, $this->timezone)->startOfDay();

        // Return formatted string if format is specified
        if ($this->format !== null) {
            return $carbon->format($this->format);
        }

        return $carbon;
    }

    /**
     * Prepare the given value for storage (from Carbon/string to DB integer).
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return int|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return (int) $value->format('Ymd');
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return (int) Carbon::parse($value, $this->timezone)->format('Ymd');
        }

        return null;
    }
}
