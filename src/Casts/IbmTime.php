<?php

namespace CodyJHeiser\Db2Eloquent\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cast for IBM time fields stored as integers in Hms format (e.g., 73733 = 07:37:33).
 *
 * Usage in model:
 *   protected $casts = [
 *       'SHTMCD' => IbmTime::class,                    // Returns Carbon instance
 *       'SHTMCD' => IbmTime::class.':H:i:s',           // Returns "07:37:33"
 *       'SHTMCD' => IbmTime::class.':time',             // Shorthand for H:i:s
 *       'SHTMCD' => IbmTime::class.':12h',              // Returns "7:37:33 AM"
 *       'SHTMCD' => IbmTime::class.':short',            // Returns "07:37"
 *   ];
 *
 * Returns Carbon instance or formatted string when getting.
 * Accepts Carbon/string/int when setting.
 */
class IbmTime implements CastsAttributes
{
    /**
     * The timezone to use for times.
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
        'time' => 'H:i:s',
        '12h' => 'g:i:s A',
        'short' => 'H:i',
    ];

    /**
     * Create a new cast instance.
     *
     * @param  string|null  $format  Optional output format (e.g., 'H:i:s', 'time', 'g:i A')
     */
    public function __construct(?string $format = null)
    {
        if ($format !== null) {
            $this->format = $this->formatShortcuts[$format] ?? $format;
        }
    }

    /**
     * Cast the given value (from DB integer to Carbon or formatted string).
     *
     * @return CarbonInterface|string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CarbonInterface|string|null
    {
        if ($value === null || $value === '' || ($value === 0 && $this->treatZeroAsNull())) {
            return null;
        }

        // Pad to 6 digits: 73733 => "073733"
        $timeString = str_pad((string) (int) $value, 6, '0', STR_PAD_LEFT);

        if (strlen($timeString) !== 6) {
            return null;
        }

        $hour = (int) substr($timeString, 0, 2);
        $minute = (int) substr($timeString, 2, 2);
        $second = (int) substr($timeString, 4, 2);

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        $carbon = Carbon::today($this->timezone)->setTime($hour, $minute, $second);

        if ($this->format !== null) {
            return $carbon->format($this->format);
        }

        return $carbon;
    }

    /**
     * Prepare the given value for storage (from Carbon/string to DB integer).
     *
     * @return int|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return (int) $value->format('His');
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return (int) Carbon::parse($value, $this->timezone)->format('His');
        }

        return null;
    }

    /**
     * Whether to treat a zero value as null.
     */
    protected function treatZeroAsNull(): bool
    {
        return true;
    }
}
