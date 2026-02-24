<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Builders\MappedBuilder;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\StatusChange;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\StatusChangeDateTime;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\StatusChangeDateTimeFormatted;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Live integration tests against R60FSDTA.FSZUPSCLOG.
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 *
 * Tests:
 * - Mapped column names in casts (IbmDate, IbmTime using mapped names)
 * - IbmDateTime composite cast (date + time columns)
 * - Column mapping, auto-filtering, exists(), selectMapped
 */
class StatusChangeModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    /**
     * Return an unfiltered query builder for the given model class.
     *
     * @template T of \CodyJHeiser\Db2Eloquent\Model
     * @param  class-string<T> $model
     * @return MappedBuilder
     */
    private function query(string $model): MappedBuilder
    {
        return $model::unfiltered();
    }

    // ==================== BASIC CONNECTIVITY ====================

    public function test_can_query_table(): void
    {
        $results = $this->query(StatusChange::class)->limit(1)->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_returns_mapped_column_names(): void
    {
        $record = $this->query(StatusChange::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        $array = $record->toArray();

        // Should have mapped column names, not raw DB names
        $this->assertArrayHasKey('change_date', $array);
        $this->assertArrayHasKey('change_time', $array);
        $this->assertArrayHasKey('customer', $array);
        $this->assertArrayHasKey('salesrep', $array);
        $this->assertArrayHasKey('status_code', $array);

        // Should NOT have raw DB column names
        $this->assertArrayNotHasKey('ZSCDATE', $array);
        $this->assertArrayNotHasKey('ZSCTIME', $array);
    }

    // ==================== MAPPED CAST NAMES ====================

    public function test_ibm_date_cast_with_mapped_name(): void
    {
        $record = $this->query(StatusChange::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        // 'change_date' is a mapped name for ZSCDATE, cast with IbmDate::class.':date'
        $dateValue = $record->change_date;

        if ($dateValue !== null) {
            // Should be a formatted date string (Y-m-d) because of :date format
            $this->assertIsString($dateValue);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateValue);
        }
    }

    public function test_ibm_date_cast_accessible_via_db_column_name(): void
    {
        $record = $this->query(StatusChange::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        // Accessing via raw DB column name should also trigger the cast
        $dateValue = $record->ZSCDATE;

        if ($dateValue !== null) {
            $this->assertIsString($dateValue);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateValue);
        }
    }

    public function test_ibm_time_cast_with_mapped_name(): void
    {
        $record = $this->query(StatusChange::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        // 'change_time' is a mapped name for ZSCTIME, cast with IbmTime::class.':time'
        $timeValue = $record->change_time;

        if ($timeValue !== null) {
            // Should be a formatted time string (H:i:s) because of :time format
            $this->assertIsString($timeValue);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $timeValue);
        }
    }

    // ==================== COMPOSITE DATETIME CAST ====================

    public function test_composite_datetime_cast_returns_carbon(): void
    {
        $record = $this->query(StatusChangeDateTime::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        // The composite cast 'change_date,change_time' should return a Carbon instance
        $dt = $record->change_date;

        if ($dt !== null) {
            $this->assertInstanceOf(Carbon::class, $dt);

            // Should have both date and time components
            $this->assertGreaterThan(2000, $dt->year);
            $this->assertLessThanOrEqual(12, $dt->month);
            $this->assertLessThanOrEqual(31, $dt->day);
        }
    }

    public function test_composite_datetime_cast_accessible_via_db_column(): void
    {
        $record = $this->query(StatusChangeDateTime::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        // Access via raw DB column name of the first column
        $dt = $record->ZSCDATE;

        if ($dt !== null) {
            $this->assertInstanceOf(Carbon::class, $dt);
        }
    }

    public function test_composite_datetime_formatted_returns_string(): void
    {
        $record = $this->query(StatusChangeDateTimeFormatted::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        $dt = $record->change_date;

        if ($dt !== null) {
            // Should be 'Y-m-d H:i:s' format
            $this->assertIsString($dt);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt);
        }
    }

    public function test_composite_datetime_in_to_array(): void
    {
        $record = $this->query(StatusChangeDateTime::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        $array = $record->toArray();

        // The composite cast should appear under the first column's mapped name
        $this->assertArrayHasKey('change_date', $array);

        if ($array['change_date'] !== null) {
            $this->assertInstanceOf(Carbon::class, $array['change_date']);
        }
    }

    // ==================== MULTIPLE RECORDS ====================

    public function test_can_fetch_multiple_records(): void
    {
        $records = $this->query(StatusChange::class)->limit(5)->get();

        $this->assertInstanceOf(Collection::class, $records);
        $this->assertLessThanOrEqual(5, $records->count());
    }

    public function test_composite_datetime_works_across_collection(): void
    {
        $records = $this->query(StatusChangeDateTime::class)->limit(5)->get();

        foreach ($records as $record) {
            $dt = $record->change_date;

            if ($dt !== null) {
                $this->assertInstanceOf(Carbon::class, $dt);
                // Basic sanity: year should be reasonable
                $this->assertGreaterThan(2000, $dt->year);
                $this->assertLessThan(2100, $dt->year);
            }
        }
    }

    // ==================== EXISTS ====================

    public function test_exists_works_on_live_table(): void
    {
        // exists() uses the override from MappedBuilder
        $exists = $this->query(StatusChange::class)->exists();

        $this->assertIsBool($exists);
    }

    public function test_doesnt_exist_works_on_live_table(): void
    {
        $doesntExist = $this->query(StatusChange::class)
            ->where('customer', 'ZZ9')
            ->doesntExist();

        $this->assertTrue($doesntExist);
    }

    // ==================== QUERY FEATURES ====================

    public function test_where_with_mapped_column_name(): void
    {
        $results = $this->query(StatusChange::class)
            ->where('status_code', '!=', '')
            ->limit(3)
            ->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_select_mapped_returns_only_mapped_columns(): void
    {
        $record = $this->query(StatusChange::class)->selectMapped()->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        $array = $record->toArray();

        // All keys should be mapped names
        foreach (array_keys($array) as $key) {
            $this->assertDoesNotMatchRegularExpression('/^[A-Z]{3,}$/', $key,
                "Key '{$key}' looks like a raw DB column name, expected mapped name");
        }
    }
}
