<?php

namespace CodyJHeiser\Db2Eloquent\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cast for IBM datetime fields.
 *
 * Supports two modes:
 * 1. Single column: a datetime stored as a large integer (e.g., 20251217073733)
 * 2. Composite columns: separate date (Ymd) and time (Hms) integer columns
 *
 * Usage in model:
 *   protected $casts = [
 *       // Single datetime column → Carbon instance
 *       'SHDTTM' => IbmDateTime::class,
 *
 *       // Two columns: date + time → Carbon instance
 *       'SHDATE,SHTIME' => IbmDateTime::class,
 *
 *       // With output format
 *       'SHDATE,SHTIME' => IbmDateTime::class.':datetime',
 *       'SHDATE,SHTIME' => IbmDateTime::class.':Y-m-d H:i:s',
 *
 *       // With mapped column names
 *       'scheduled_date,scheduled_time' => IbmDateTime::class,
 *   ];
 *
 * Returns Carbon instance or formatted string when getting.
 * Accepts Carbon/string/int when setting.
 */
class IbmDateTime implements CastsAttributes
{
    /**
     * The timezone to use.
     */
    protected string $timezone = 'America/Chicago';

    /**
     * The input format for the date portion.
     */
    protected string $dateFormat;

    /**
     * The input format for the time portion.
     */
    protected string $timeFormat;

    /**
     * The output format (null returns Carbon instance).
     */
    protected ?string $outputFormat = null;

    /**
     * Format shortcuts.
     */
    protected array $formatShortcuts = [
        'datetime' => 'Y-m-d H:i:s',
        'date' => 'Y-m-d',
        'us' => 'm/d/Y g:i:s A',
        'eu' => 'd/m/Y H:i:s',
    ];

    /**
     * Create a new cast instance.
     *
     * Accepts 0-3 arguments depending on usage:
     *   - IbmDateTime::class                             → defaults for everything
     *   - IbmDateTime::class.':datetime'                 → output format only
     *   - IbmDateTime::class.':Y-m-d H:i:s'             → custom output format
     *   - IbmDateTime::class.':Ymd,His'                  → custom input date,time formats
     *   - IbmDateTime::class.':Ymd,His,Y-m-d H:i:s'     → custom input + output formats
     *
     * Note: Arguments after ':' are comma-separated by Laravel's cast resolver.
     */
    public function __construct(string ...$args)
    {
        $this->dateFormat = 'Ymd';
        $this->timeFormat = 'His';

        if (count($args) === 1) {
            // Single arg = output format
            $this->outputFormat = $this->formatShortcuts[$args[0]] ?? $args[0];
        } elseif (count($args) === 2) {
            // Two args = input date format, input time format
            $this->dateFormat = $args[0];
            $this->timeFormat = $args[1];
        } elseif (count($args) >= 3) {
            // Three args = input date format, input time format, output format
            $this->dateFormat = $args[0];
            $this->timeFormat = $args[1];
            $this->outputFormat = $this->formatShortcuts[$args[2]] ?? $args[2];
        }
    }

    /**
     * Cast the given value (from DB integer(s) to Carbon or formatted string).
     *
     * @return CarbonInterface|string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CarbonInterface|string|null
    {
        if ($this->isCompositeKey($key)) {
            return $this->getComposite($key, $attributes);
        }

        return $this->getSingle($value);
    }

    /**
     * Handle single-column datetime (e.g., 20251217073733).
     */
    protected function getSingle(mixed $value): CarbonInterface|string|null
    {
        if ($value === null || $value === 0 || $value === '') {
            return null;
        }

        $str = (string) $value;
        $dateLen = strlen(Carbon::now()->format($this->dateFormat));
        $timeLen = strlen(Carbon::now()->format($this->timeFormat));
        $expectedLen = $dateLen + $timeLen;

        // Pad to expected length
        $str = str_pad($str, $expectedLen, '0', STR_PAD_LEFT);

        if (strlen($str) !== $expectedLen) {
            return null;
        }

        $datePart = substr($str, 0, $dateLen);
        $timePart = substr($str, $dateLen);

        return $this->buildCarbon($datePart, $timePart);
    }

    /**
     * Handle composite columns (date + time in separate columns).
     */
    protected function getComposite(string $key, array $attributes): CarbonInterface|string|null
    {
        [$dateCol, $timeCol] = $this->parseCompositeKey($key);

        $dateValue = $attributes[$dateCol] ?? null;
        $timeValue = $attributes[$timeCol] ?? null;

        if ($dateValue === null || $dateValue === 0 || $dateValue === '') {
            return null;
        }

        $datePart = (string) $dateValue;
        $dateLen = strlen(Carbon::now()->format($this->dateFormat));

        if (strlen($datePart) !== $dateLen) {
            return null;
        }

        // Time is optional for composite — default to midnight
        if ($timeValue === null || $timeValue === 0 || $timeValue === '') {
            $timeLen = strlen(Carbon::now()->format($this->timeFormat));
            $timePart = str_repeat('0', $timeLen);
        } else {
            $timeLen = strlen(Carbon::now()->format($this->timeFormat));
            $timePart = str_pad((string) (int) $timeValue, $timeLen, '0', STR_PAD_LEFT);
        }

        return $this->buildCarbon($datePart, $timePart);
    }

    /**
     * Build a Carbon instance from date and time strings, then format if needed.
     */
    protected function buildCarbon(string $datePart, string $timePart): CarbonInterface|string|null
    {
        $format = $this->dateFormat . $this->timeFormat;
        $combined = $datePart . $timePart;

        try {
            $carbon = Carbon::createFromFormat($format, $combined, $this->timezone);
        } catch (\Exception) {
            return null;
        }

        if (!$carbon) {
            return null;
        }

        if ($this->outputFormat !== null) {
            return $carbon->format($this->outputFormat);
        }

        return $carbon;
    }

    /**
     * Prepare the given value for storage (from Carbon/string to DB integer(s)).
     *
     * @return int|array|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|array|null
    {
        if ($value === null) {
            if ($this->isCompositeKey($key)) {
                [$dateCol, $timeCol] = $this->parseCompositeKey($key);
                return [$dateCol => null, $timeCol => null];
            }
            return null;
        }

        if (!$value instanceof CarbonInterface) {
            if (is_int($value)) {
                // For composite, this doesn't make sense — just return as-is for single
                if (!$this->isCompositeKey($key)) {
                    return $value;
                }
            }

            if (is_string($value)) {
                $value = Carbon::parse($value, $this->timezone);
            } else {
                return null;
            }
        }

        if ($this->isCompositeKey($key)) {
            [$dateCol, $timeCol] = $this->parseCompositeKey($key);
            return [
                $dateCol => (int) $value->format($this->dateFormat),
                $timeCol => (int) $value->format($this->timeFormat),
            ];
        }

        return (int) $value->format($this->dateFormat . $this->timeFormat);
    }

    /**
     * Check if the cast key references composite columns.
     */
    protected function isCompositeKey(string $key): bool
    {
        return str_contains($key, ',');
    }

    /**
     * Parse a composite key into its column names.
     *
     * @return array{0: string, 1: string}
     */
    protected function parseCompositeKey(string $key): array
    {
        $parts = array_map('trim', explode(',', $key));

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "IbmDateTime composite cast key must have exactly 2 columns, got: '{$key}'"
            );
        }

        return $parts;
    }
}
