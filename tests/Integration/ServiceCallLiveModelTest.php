<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Builders\MappedBuilder;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallLive;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Live integration tests against R60FILES.SBSCHD.
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 *
 * Tests:
 * - Column mapping (including special char column SHTEC#)
 * - IbmDate casts using raw DB column names
 * - exists(), selectMapped, where with mapped names
 */
class ServiceCallLiveModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    /**
     * @template T of \CodyJHeiser\Db2Eloquent\Model
     * @param  class-string<T> $model
     */
    private function query(string $model): MappedBuilder
    {
        return $model::unfiltered();
    }

    // ==================== BASIC CONNECTIVITY ====================

    public function test_can_query_table(): void
    {
        $results = $this->query(ServiceCallLive::class)->limit(1)->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count(), 'Expected at least 1 service call');
    }

    // ==================== COLUMN MAPPING ====================

    public function test_returns_mapped_column_names(): void
    {
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $array = $record->toArray();

        $this->assertArrayHasKey('call_number', $array);
        $this->assertArrayHasKey('customer_number', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('call_date', $array);
        $this->assertArrayHasKey('scheduled_date', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('state', $array);
        $this->assertArrayHasKey('technician_number', $array);

        // Should NOT have raw DB column names
        $this->assertArrayNotHasKey('SHCLNO', $array);
        $this->assertArrayNotHasKey('SHCUST', $array);
        $this->assertArrayNotHasKey('SHCLDT', $array);
    }

    public function test_accessible_via_mapped_and_db_names(): void
    {
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $this->assertEquals($record->call_number, $record->SHCLNO);
        $this->assertEquals($record->customer_number, $record->SHCUST);
        $this->assertEquals($record->status, $record->SHCLST);
        $this->assertEquals($record->city, $record->SHCITY);
    }

    public function test_special_char_column_mapping(): void
    {
        // SHTEC# contains a special character in the column name
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $array = $record->toArray();
        $this->assertArrayHasKey('technician_number', $array);

        // Access via mapped name
        $tech = $record->technician_number;
        $this->assertNotNull($tech);
    }

    // ==================== IBM DATE CASTS ====================

    public function test_call_date_cast_returns_formatted_date(): void
    {
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $callDate = $record->call_date;

        if ($callDate !== null) {
            $this->assertIsString($callDate);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $callDate,
                "call_date should be Y-m-d format, got: {$callDate}");
        }
    }

    public function test_scheduled_date_cast_returns_formatted_date(): void
    {
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $scheduledDate = $record->scheduled_date;

        if ($scheduledDate !== null) {
            $this->assertIsString($scheduledDate);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate,
                "scheduled_date should be Y-m-d format, got: {$scheduledDate}");
        }
    }

    public function test_date_cast_accessible_via_db_column_name(): void
    {
        $record = $this->query(ServiceCallLive::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        // Both mapped and raw should return the same cast value
        $this->assertEquals($record->call_date, $record->SHCLDT);
        $this->assertEquals($record->scheduled_date, $record->SHSCDT);
    }

    // ==================== SELECT MAPPED ====================

    public function test_select_mapped_returns_only_mapped_columns(): void
    {
        $record = $this->query(ServiceCallLive::class)->selectMapped()->limit(1)->first();
        $this->assertNotNull($record, 'No data in SBSCHD table');

        $array = $record->toArray();

        foreach (array_keys($array) as $key) {
            $this->assertDoesNotMatchRegularExpression('/^[A-Z]{2,}[#]?$/', $key,
                "Key '{$key}' looks like a raw DB column name, expected mapped name");
        }
    }

    // ==================== WHERE WITH MAPPED NAMES ====================

    public function test_where_with_mapped_column_name(): void
    {
        $results = $this->query(ServiceCallLive::class)
            ->where('status', '!=', '')
            ->limit(3)
            ->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    // ==================== EXISTS ====================

    public function test_exists_works(): void
    {
        $exists = $this->query(ServiceCallLive::class)->exists();

        $this->assertTrue($exists);
    }

    // ==================== MULTIPLE RECORDS ====================

    public function test_can_fetch_multiple_records(): void
    {
        $records = $this->query(ServiceCallLive::class)->limit(10)->get();

        $this->assertInstanceOf(Collection::class, $records);
        $this->assertGreaterThan(0, $records->count());
        $this->assertLessThanOrEqual(10, $records->count());

        // All records should have date casts applied consistently
        foreach ($records as $record) {
            $callDate = $record->call_date;
            if ($callDate !== null) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $callDate);
            }
        }
    }
}
