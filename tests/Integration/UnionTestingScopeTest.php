<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\Customer;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallAll;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallHistory;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallLive;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\StatusChange;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Integration tests for the testing() scope with union models.
 *
 * Verifies that testing() (R60FILES → T60FILES schema swap) propagates
 * correctly through:
 * - Union model outer query
 * - Union source subqueries (SBSCHD, SBHSHD in test schema)
 * - Relationships from/to union models
 * - Child models (ServiceCallLive, ServiceCallHistory)
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 * All queries use limit() since these tables have millions of rows.
 */
class UnionTestingScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    // ==================== UNION MODEL WITH TESTING SCOPE ====================

    public function test_union_model_testing_scope_queries_test_schema(): void
    {
        $query = ServiceCallAll::testing()->unfiltered()->limit(1);
        $sql = $query->toSql();

        // The inner source queries should reference T60FILES, not R60FILES
        $this->assertStringContainsString('T60FILES', $sql,
            "Expected T60FILES in SQL, got: {$sql}");
        $this->assertStringNotContainsString('R60FILES', $sql,
            "Should not contain R60FILES when testing() is active, got: {$sql}");
    }

    public function test_union_model_testing_scope_returns_data(): void
    {
        $results = ServiceCallAll::testing()->unfiltered()->limit(5)->get();

        $this->assertNotEmpty($results, 'No data in test schema union query');

        $record = $results->first();
        $this->assertInstanceOf(ServiceCallAll::class, $record);
        $this->assertNotNull($record->call_number);
        $this->assertNotNull($record->customer_number);
    }

    public function test_union_model_testing_scope_source_column_shows_test_schema(): void
    {
        $record = ServiceCallAll::testing()->unfiltered()->limit(1)->first();
        $this->assertNotNull($record, 'No data in test schema union query');

        // _source should reference T60FILES tables, not R60FILES
        $this->assertNotNull($record->_source);
        $this->assertStringContainsString('T60FILES', $record->_source,
            "Expected _source to reference T60FILES, got: {$record->_source}");
    }

    // ==================== CHILD MODELS WITH TESTING SCOPE ====================

    public function test_child_model_live_testing_scope_queries_test_schema(): void
    {
        $query = ServiceCallLive::testing()->unfiltered()->limit(1);
        $sql = $query->toSql();

        $this->assertStringContainsString('T60FILES', $sql,
            "Expected T60FILES in SQL, got: {$sql}");
    }

    public function test_child_model_live_testing_scope_returns_data(): void
    {
        $results = ServiceCallLive::testing()->unfiltered()->limit(3)->get();

        $this->assertNotEmpty($results, 'No data in T60FILES.SBSCHD');

        foreach ($results as $record) {
            $this->assertInstanceOf(ServiceCallLive::class, $record);
            $this->assertNotNull($record->call_number);
        }
    }

    public function test_child_model_history_testing_scope_returns_data(): void
    {
        $results = ServiceCallHistory::testing()->unfiltered()->limit(3)->get();

        $this->assertNotEmpty($results, 'No data in T60FILES.SBHSHD');

        foreach ($results as $record) {
            $this->assertInstanceOf(ServiceCallHistory::class, $record);
            $this->assertNotNull($record->call_number);
        }
    }

    // ==================== RELATIONSHIPS WITH TESTING SCOPE ====================

    public function test_union_model_relationship_with_testing_scope(): void
    {
        // ServiceCallAll::testing() -> customer() should also use test schema
        $record = ServiceCallAll::testing()->unfiltered()
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();
        $this->assertNotNull($record, 'No service calls with customer_number in test schema');

        $customerQuery = $record->customer();
        $sql = $customerQuery->toSql();

        // The customer query should use test schema too (propagated via newRelatedInstance)
        $this->assertStringContainsString('T60FILES', $sql,
            "Expected T60FILES in customer relationship SQL, got: {$sql}");
    }

    public function test_union_model_relationship_with_testing_scope_returns_data(): void
    {
        $record = ServiceCallAll::testing()->unfiltered()
            ->where('customer_number', '!=', '')
            ->where('customer_number', '!=', 0)
            ->limit(1)
            ->first();
        $this->assertNotNull($record, 'No service calls with customer_number in test schema');

        $customer = $record->customer()->first();

        if ($customer === null) {
            $this->markTestSkipped('No matching customer in test schema for: ' . $record->customer_number);
        }

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($record->customer_number, $customer->customer_number);
    }

    // ==================== BELONGS TO UNION WITH TESTING SCOPE ====================

    public function test_belongs_to_union_with_testing_scope_query_builds(): void
    {
        $record = StatusChange::testing()->unfiltered()->limit(1)->first();
        $this->assertNotNull($record, 'No data in test schema FSZUPSCLOG');

        $query = $record->serviceCall();
        $sql = $query->toSql();

        // The union subquery should use test schema tables
        $this->assertStringContainsString('T60', $sql,
            "Expected test schema in serviceCall() SQL, got: {$sql}");
    }

    public function test_where_has_union_with_testing_scope(): void
    {
        $results = StatusChange::testing()->unfiltered()
            ->whereHas('serviceCall')
            ->limit(3)
            ->get();

        $this->assertNotEmpty($results, 'whereHas(serviceCall) with testing() returned no results');

        foreach ($results as $record) {
            $this->assertInstanceOf(StatusChange::class, $record);
        }
    }

    public function test_with_where_has_union_with_testing_scope(): void
    {
        $result = StatusChange::testing()->unfiltered()
            ->withWhereHas('serviceCall')
            ->limit(1)
            ->first();

        $this->assertNotNull($result, 'withWhereHas(serviceCall) with testing() returned no results');
        $this->assertTrue($result->relationLoaded('serviceCall'));
        $this->assertNotNull($result->serviceCall);
        $this->assertInstanceOf(ServiceCallAll::class, $result->serviceCall);
    }

    // ==================== TESTING SCOPE DOES NOT LEAK ====================

    public function test_default_query_still_uses_prod_schema(): void
    {
        // After running testing() queries above, a fresh query should use prod schema
        $query = ServiceCallAll::unfiltered()->limit(1);
        $sql = $query->toSql();

        $this->assertStringContainsString('R60FILES', $sql,
            "Default query should use R60FILES, got: {$sql}");
    }

    public function test_child_model_default_query_still_uses_prod_schema(): void
    {
        $query = ServiceCallLive::unfiltered()->limit(1);
        $sql = $query->toSql();

        $this->assertStringContainsString('R60FILES', $sql,
            "Default query should use R60FILES, got: {$sql}");
    }
}
