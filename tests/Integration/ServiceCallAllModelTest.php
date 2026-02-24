<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Builders\ReadOnlyMappedBuilder;
use CodyJHeiser\Db2Eloquent\Exceptions\ReadOnlyModelException;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallAll;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Live integration tests for ServiceCallAll (UNION ALL of SBSCHD + SBHSHD).
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 * All queries use limit() since these tables have millions of rows.
 */
class ServiceCallAllModelTest extends TestCase
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
    private function query(string $model): ReadOnlyMappedBuilder
    {
        return $model::unfiltered();
    }

    // ==================== BASIC CONNECTIVITY ====================

    public function test_can_query_union(): void
    {
        $results = $this->query(ServiceCallAll::class)->limit(5)->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count(), 'Expected at least 1 service call from union');
    }

    // ==================== COLUMN MAPPING ====================

    public function test_returns_mapped_column_names(): void
    {
        $record = $this->query(ServiceCallAll::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in union query');

        $array = $record->toArray();

        $this->assertArrayHasKey('call_number', $array);
        $this->assertArrayHasKey('customer_number', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('state', $array);
        $this->assertArrayHasKey('technician_number', $array);

        // Should NOT have raw DB column names
        $this->assertArrayNotHasKey('SHCLNO', $array);
        $this->assertArrayNotHasKey('SHCUST', $array);
    }

    public function test_property_access_via_mapped_names(): void
    {
        $record = $this->query(ServiceCallAll::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in union query');

        $this->assertNotNull($record->call_number);
        $this->assertNotNull($record->customer_number);
    }

    // ==================== QUERY OPERATIONS ====================

    public function test_where_with_mapped_column_name(): void
    {
        $results = $this->query(ServiceCallAll::class)
            ->where('status', '!=', '')
            ->limit(3)
            ->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_exists_works(): void
    {
        $exists = $this->query(ServiceCallAll::class)->limit(1)->exists();

        $this->assertTrue($exists);
    }

    public function test_first_returns_correct_model_type(): void
    {
        $record = $this->query(ServiceCallAll::class)->limit(1)->first();

        $this->assertInstanceOf(ServiceCallAll::class, $record);
    }

    public function test_limit_works(): void
    {
        $results = $this->query(ServiceCallAll::class)->limit(3)->get();

        $this->assertLessThanOrEqual(3, $results->count());
    }

    // ==================== FILTERED VS UNFILTERED ====================

    public function test_default_query_applies_filters(): void
    {
        $filteredCount = ServiceCallAll::limit(50)->count();
        $unfilteredCount = ServiceCallAll::unfiltered()->limit(50)->count();

        $this->assertGreaterThanOrEqual($filteredCount, $unfilteredCount,
            'Unfiltered should return at least as many results as filtered');
    }

    // ==================== WRITE PREVENTION ====================

    public function test_save_throws_read_only_exception(): void
    {
        $record = $this->query(ServiceCallAll::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in union query');

        $this->expectException(ReadOnlyModelException::class);
        $record->save();
    }

    public function test_delete_throws_read_only_exception(): void
    {
        $record = $this->query(ServiceCallAll::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in union query');

        $this->expectException(ReadOnlyModelException::class);
        $record->delete();
    }

    public function test_builder_insert_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        ServiceCallAll::query()->insert(['call_number' => 99999]);
    }

    public function test_builder_update_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        ServiceCallAll::query()->update(['status' => 'X']);
    }
}
