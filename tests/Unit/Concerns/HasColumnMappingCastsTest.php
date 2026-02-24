<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Casts\IbmDateTime;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestSchedule;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Tests for mapped column names in $casts and composite (multi-column) casts.
 */
class HasColumnMappingCastsTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();
    }

    // ==================== MAPPED NAMES IN CASTS ====================

    public function test_mapped_name_in_casts_is_translated_to_db_column(): void
    {
        // TestSchedule uses 'scheduled_date,scheduled_time' => IbmDateTime::class
        // This should be translated to 'SHDATE,SHTIME' => IbmDateTime::class
        $model = new TestSchedule();
        $casts = $model->getCasts();

        $this->assertArrayHasKey('SHDATE,SHTIME', $casts);
        $this->assertArrayNotHasKey('scheduled_date,scheduled_time', $casts);
    }

    public function test_single_mapped_cast_name_is_translated(): void
    {
        // TestItem uses 'ICDATE' => IbmDate::class
        // Let's verify a model using mapped name works by checking getCasts
        $model = new class extends \CodyJHeiser\Db2Eloquent\Model {
            protected $connection = 'testing';
            protected $table = 'test_items';
            protected string $schema = '';
            protected array $maps = [
                'ICITEM' => 'item_number',
                'ICDATE' => 'created_date',
            ];
            protected $casts = [
                'created_date' => IbmDate::class,
            ];

            public function getTable(): string
            {
                return $this->table;
            }
        };

        $casts = $model->getCasts();

        // 'created_date' should be translated to 'ICDATE'
        $this->assertArrayHasKey('ICDATE', $casts);
        $this->assertArrayNotHasKey('created_date', $casts);
    }

    public function test_db_column_name_in_casts_still_works(): void
    {
        // Original behavior: DB column names in casts should still work
        $model = new TestItem();
        $casts = $model->getCasts();

        $this->assertArrayHasKey('ICDATE', $casts);
    }

    // ==================== COMPOSITE CAST — GET ====================

    public function test_composite_cast_get_via_db_column_name(): void
    {
        $this->insertSchedule('SCH1', 'Test', 20251217, 143052);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH1')->first();

        $result = $schedule->SHDATE;

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(12, $result->month);
        $this->assertEquals(17, $result->day);
        $this->assertEquals(14, $result->hour);
        $this->assertEquals(30, $result->minute);
        $this->assertEquals(52, $result->second);
    }

    public function test_composite_cast_get_via_mapped_name(): void
    {
        $this->insertSchedule('SCH2', 'Test', 20251217, 73733);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH2')->first();

        $result = $schedule->scheduled_date;

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(7, $result->hour);
        $this->assertEquals(37, $result->minute);
    }

    public function test_composite_cast_returns_null_when_date_is_null(): void
    {
        $this->insertSchedule('SCH3', 'Test', 0, 143052);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH3')->first();

        $this->assertNull($schedule->scheduled_date);
    }

    public function test_composite_cast_defaults_time_to_midnight(): void
    {
        $this->insertSchedule('SCH4', 'Test', 20251217, 0);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH4')->first();

        $result = $schedule->scheduled_date;

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(0, $result->hour);
        $this->assertEquals(0, $result->minute);
    }

    // ==================== COMPOSITE CAST — SET ====================

    public function test_composite_cast_set_via_mapped_name(): void
    {
        $this->insertSchedule('SCH5', 'Test', 0, 0);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH5')->first();
        $schedule->scheduled_date = Carbon::create(2025, 12, 17, 14, 30, 52);

        // Verify raw attributes were set correctly
        $raw = $schedule->getRawAttributes();
        $this->assertEquals(20251217, $raw['SHDATE']);
        $this->assertEquals(143052, $raw['SHTIME']);
    }

    public function test_composite_cast_set_via_db_column_name(): void
    {
        $this->insertSchedule('SCH6', 'Test', 0, 0);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH6')->first();
        $schedule->SHDATE = Carbon::create(2025, 6, 15, 9, 0, 0);

        $raw = $schedule->getRawAttributes();
        $this->assertEquals(20250615, $raw['SHDATE']);
        $this->assertEquals(90000, $raw['SHTIME']);
    }

    public function test_composite_cast_set_from_string(): void
    {
        $this->insertSchedule('SCH7', 'Test', 0, 0);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH7')->first();
        $schedule->scheduled_date = '2025-12-17 14:30:52';

        $raw = $schedule->getRawAttributes();
        $this->assertEquals(20251217, $raw['SHDATE']);
        $this->assertEquals(143052, $raw['SHTIME']);
    }

    public function test_composite_cast_set_null(): void
    {
        $this->insertSchedule('SCH8', 'Test', 20251217, 143052);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH8')->first();
        $schedule->scheduled_date = null;

        $raw = $schedule->getRawAttributes();
        $this->assertNull($raw['SHDATE']);
        $this->assertNull($raw['SHTIME']);
    }

    // ==================== TO ARRAY ====================

    public function test_composite_cast_appears_in_to_array(): void
    {
        $this->insertSchedule('SCH9', 'Test', 20251217, 143052);

        $schedule = TestSchedule::unfiltered()->where('SHCODE', 'SCH9')->first();
        $array = $schedule->toArray();

        // Should appear under the mapped name of the first column
        $this->assertArrayHasKey('scheduled_date', $array);
        $this->assertInstanceOf(Carbon::class, $array['scheduled_date']);
        $this->assertEquals(2025, $array['scheduled_date']->year);
        $this->assertEquals(14, $array['scheduled_date']->hour);
    }

    // ==================== COMPOSITE CAST WITH FORMAT ====================

    public function test_composite_cast_with_output_format(): void
    {
        $model = new class extends \CodyJHeiser\Db2Eloquent\Model {
            protected $connection = 'testing';
            protected $table = 'test_schedules';
            protected string $schema = '';
            protected array $maps = [
                'SHCODE' => 'schedule_code',
                'SHDATE' => 'scheduled_date',
                'SHTIME' => 'scheduled_time',
                'SHCOMP' => 'company_number',
                'SHDLTC' => 'delete_code',
            ];
            protected $casts = [
                'scheduled_date,scheduled_time' => IbmDateTime::class . ':datetime',
            ];

            public function getTable(): string
            {
                return $this->table;
            }
        };

        $this->insertSchedule('SCHF', 'Test', 20251217, 143052);

        $schedule = $model->newQuery()->where('SHCODE', 'SCHF')->first();

        $this->assertEquals('2025-12-17 14:30:52', $schedule->scheduled_date);
    }

    // ==================== HELPERS ====================

    protected function insertSchedule(string $code, string $desc, int $date, int $time): void
    {
        $this->app['db']->connection('testing')->table('test_schedules')->insert([
            'SHCODE' => $code,
            'SHDESC' => $desc,
            'SHDATE' => $date,
            'SHTIME' => $time,
            'SHCOMP' => '1',
            'SHDLTC' => 'A',
        ]);
    }
}
