<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\Customer;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallAll;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallHistory;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallLive;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\StatusChange;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests for relationships involving union models.
 *
 * Covers:
 * - belongsTo a union model (StatusChange -> ServiceCallAll)
 * - Union model's own belongsTo (ServiceCallAll -> Customer)
 * - whereHas / withWhereHas through union models
 * - Eager loading (with) through union models
 * - Child model relationships still work normally
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 * All queries use limit() since these tables have millions of rows.
 */
class UnionRelationshipsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    // ==================== BELONGS TO UNION MODEL ====================

    public function test_belongs_to_union_query_builds(): void
    {
        $record = StatusChange::unfiltered()->limit(1)->first();
        $this->assertNotNull($record, 'No data in FSZUPSCLOG table');

        $query = $record->serviceCall();
        $sql = $query->toSql();
        $this->assertNotNull($sql);

        // The SQL should reference union_view, not the raw table
        $this->assertStringContainsString('union_view', $sql,
            'Expected union_view alias in relationship query SQL');
    }

    public function test_belongs_to_union_returns_data(): void
    {
        // Find a status change whose call_number exists in a service call table
        $match = DB::connection('vai')->selectOne(
            'SELECT z.ZSCCLNO FROM R60FSDTA.FSZUPSCLOG z '
            . 'INNER JOIN R60FILES.SBSCHD s ON s.SHCLNO = z.ZSCCLNO AND s.SHCMP = 1 '
            . 'WHERE z.ZSCCLNO > 0 FETCH FIRST 1 ROWS ONLY'
        );

        if ($match === null) {
            $this->markTestSkipped('No overlapping call_number between FSZUPSCLOG and SBSCHD');
        }

        $statusChange = StatusChange::unfiltered()
            ->where('call_number', $match->ZSCCLNO)
            ->first();
        $this->assertNotNull($statusChange);

        $serviceCall = $statusChange->serviceCall()->first();

        $this->assertNotNull($serviceCall, "serviceCall() returned null for call_number: {$match->ZSCCLNO}");
        $this->assertInstanceOf(ServiceCallAll::class, $serviceCall);
        $this->assertEquals($statusChange->call_number, $serviceCall->call_number);
    }

    // ==================== UNION MODEL'S OWN BELONGS TO ====================

    public function test_union_model_belongs_to_customer_query_builds(): void
    {
        $record = ServiceCallAll::unfiltered()->limit(1)->first();
        $this->assertNotNull($record, 'No data in union query');

        $query = $record->customer();
        $sql = $query->toSql();
        $this->assertNotNull($sql);
    }

    public function test_union_model_belongs_to_customer_returns_data(): void
    {
        $record = ServiceCallAll::unfiltered()
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();
        $this->assertNotNull($record, 'No service calls with customer_number');

        $customer = $record->customer()->first();

        if ($customer === null) {
            $this->markTestSkipped('No matching customer for customer_number: ' . $record->customer_number);
        }

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($record->customer_number, $customer->customer_number);
    }

    // ==================== WHERE HAS (EXISTS SUBQUERY) ====================

    public function test_where_has_on_belongs_to_union(): void
    {
        $results = StatusChange::unfiltered()
            ->whereHas('serviceCall')
            ->limit(5)
            ->get();

        $this->assertNotEmpty($results, 'whereHas(serviceCall) returned no results');

        foreach ($results as $record) {
            $this->assertInstanceOf(StatusChange::class, $record);
            $this->assertGreaterThan(0, $record->call_number);
        }
    }

    public function test_where_has_on_union_models_own_relationship(): void
    {
        // ServiceCallAll::whereHas('customer') — union model checking its own relationship
        $results = ServiceCallAll::unfiltered()
            ->whereHas('customer')
            ->limit(5)
            ->get();

        $this->assertNotEmpty($results, 'ServiceCallAll::whereHas(customer) returned no results');

        foreach ($results as $record) {
            $this->assertInstanceOf(ServiceCallAll::class, $record);
            $this->assertNotEmpty($record->customer_number);
        }
    }

    // ==================== WITH WHERE HAS (EAGER LOAD + FILTER) ====================

    public function test_with_where_has_belongs_to_union(): void
    {
        $result = StatusChange::unfiltered()
            ->withWhereHas('serviceCall')
            ->limit(1)
            ->first();

        $this->assertNotNull($result, 'withWhereHas(serviceCall) returned no results');
        $this->assertInstanceOf(StatusChange::class, $result);

        // Relation should be eager loaded
        $this->assertTrue($result->relationLoaded('serviceCall'));
        $this->assertNotNull($result->serviceCall);
        $this->assertInstanceOf(ServiceCallAll::class, $result->serviceCall);
        $this->assertEquals($result->call_number, $result->serviceCall->call_number);
    }

    public function test_with_where_has_on_union_models_own_relationship(): void
    {
        $result = ServiceCallAll::unfiltered()
            ->withWhereHas('customer')
            ->limit(1)
            ->first();

        $this->assertNotNull($result, 'ServiceCallAll::withWhereHas(customer) returned no results');
        $this->assertInstanceOf(ServiceCallAll::class, $result);

        $this->assertTrue($result->relationLoaded('customer'));
        $this->assertNotNull($result->customer);
        $this->assertInstanceOf(Customer::class, $result->customer);
    }

    // ==================== EAGER LOADING (WITH) ====================

    public function test_eager_load_belongs_to_union(): void
    {
        // Find a call number we know exists in live table
        $match = DB::connection('vai')->selectOne(
            'SELECT z.ZSCCLNO FROM R60FSDTA.FSZUPSCLOG z '
            . 'INNER JOIN R60FILES.SBSCHD s ON s.SHCLNO = z.ZSCCLNO AND s.SHCMP = 1 '
            . 'WHERE z.ZSCCLNO > 0 FETCH FIRST 1 ROWS ONLY'
        );

        if ($match === null) {
            $this->markTestSkipped('No overlapping call_number between FSZUPSCLOG and SBSCHD');
        }

        $result = StatusChange::unfiltered()
            ->with('serviceCall')
            ->where('call_number', $match->ZSCCLNO)
            ->limit(1)
            ->first();

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('serviceCall'));
        $this->assertNotNull($result->serviceCall);
        $this->assertEquals($match->ZSCCLNO, $result->serviceCall->call_number);
    }

    public function test_eager_load_union_models_own_relationship(): void
    {
        $result = ServiceCallAll::unfiltered()
            ->with('customer')
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();

        $this->assertNotNull($result, 'No service calls with customer_number');
        $this->assertTrue($result->relationLoaded('customer'));

        if ($result->customer !== null) {
            $this->assertInstanceOf(Customer::class, $result->customer);
            $this->assertEquals($result->customer_number, $result->customer->customer_number);
        }
    }

    // ==================== CHILD MODEL RELATIONSHIPS UNAFFECTED ====================

    public function test_child_model_belongs_to_still_works(): void
    {
        // ServiceCallLive extends ServiceCallAll but should use normal table qualifiers
        $record = ServiceCallLive::unfiltered()
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();
        $this->assertNotNull($record, 'No service calls with customer_number');

        $customer = $record->customer()->first();

        if ($customer === null) {
            $this->markTestSkipped('No matching customer for customer_number: ' . $record->customer_number);
        }

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($record->customer_number, $customer->customer_number);
    }

    public function test_child_model_where_has_still_works(): void
    {
        $results = ServiceCallLive::unfiltered()
            ->whereHas('customer')
            ->limit(3)
            ->get();

        $this->assertNotEmpty($results, 'ServiceCallLive::whereHas(customer) returned no results');
    }

    public function test_child_model_with_where_has_still_works(): void
    {
        $result = ServiceCallLive::unfiltered()
            ->withWhereHas('customer')
            ->limit(1)
            ->first();

        $this->assertNotNull($result, 'ServiceCallLive::withWhereHas(customer) returned no results');
        $this->assertTrue($result->relationLoaded('customer'));
        $this->assertNotNull($result->customer);
        $this->assertInstanceOf(Customer::class, $result->customer);
    }

    public function test_history_child_model_relationships_work(): void
    {
        $record = ServiceCallHistory::unfiltered()
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();
        $this->assertNotNull($record, 'No service calls in SBHSHD');

        $customer = $record->customer()->first();

        if ($customer === null) {
            $this->markTestSkipped('No matching customer for customer_number: ' . $record->customer_number);
        }

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($record->customer_number, $customer->customer_number);
    }
}
