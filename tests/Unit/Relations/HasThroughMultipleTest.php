<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Relations;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestCategory;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestDepartment;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItemRelation;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

/**
 * Tests for HasOneThroughMultiple and HasManyThroughMultiple relations.
 * Verifies multi-column through relationships work correctly with DB2-compatible SQL.
 */
class HasThroughMultipleTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        Model::preventLazyLoading(false);

        // Seed production departments
        $this->app['db']->connection('testing')->table('test_departments')->insert([
            ['DPCODE' => 'DEPT1', 'DPNAME' => 'Prod Department 1', 'DPCOMP' => '1', 'DPDLTC' => 'A'],
            ['DPCODE' => 'DEPT2', 'DPNAME' => 'Prod Department 2', 'DPCOMP' => '1', 'DPDLTC' => 'A'],
            ['DPCODE' => 'DEPT1', 'DPNAME' => 'Prod Department 1 Co2', 'DPCOMP' => '2', 'DPDLTC' => 'A'],
        ]);

        // Seed production categories with department reference
        $this->app['db']->connection('testing')->table('test_categories')->insert([
            ['CTCODE' => 'CAT1', 'CTNAME' => 'Prod Category 1', 'CTCOMP' => '1', 'CTDEPT' => 'DEPT1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT2', 'CTNAME' => 'Prod Category 2', 'CTCOMP' => '1', 'CTDEPT' => 'DEPT1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT3', 'CTNAME' => 'Prod Category 3', 'CTCOMP' => '1', 'CTDEPT' => 'DEPT2', 'CTDLTC' => 'A'],
        ]);

        // Seed production items
        $this->app['db']->connection('testing')->table('test_items_rel')->insert([
            ['ITCODE' => 'ITEM1', 'ITNAME' => 'Prod Item 1', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM2', 'ITNAME' => 'Prod Item 2', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM3', 'ITNAME' => 'Prod Item 3', 'ITCAT' => 'CAT2', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM4', 'ITNAME' => 'Prod Item 4', 'ITCAT' => 'CAT3', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
        ]);

        // Seed TEST schema tables
        $this->app['db']->connection('testing')->table('t60_test_departments')->insert([
            ['DPCODE' => 'DEPT1', 'DPNAME' => 'TEST Department 1', 'DPCOMP' => '1', 'DPDLTC' => 'A'],
        ]);

        $this->app['db']->connection('testing')->table('t60_test_categories')->insert([
            ['CTCODE' => 'CAT1', 'CTNAME' => 'TEST Category 1', 'CTCOMP' => '1', 'CTDEPT' => 'DEPT1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT2', 'CTNAME' => 'TEST Category 2', 'CTCOMP' => '1', 'CTDEPT' => 'DEPT1', 'CTDLTC' => 'A'],
        ]);

        $this->app['db']->connection('testing')->table('t60_test_items_rel')->insert([
            ['ITCODE' => 'TITEM1', 'ITNAME' => 'TEST Item 1', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'TITEM2', 'ITNAME' => 'TEST Item 2', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'TITEM3', 'ITNAME' => 'TEST Item 3', 'ITCAT' => 'CAT2', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
        ]);
    }

    // ==================== HAS ONE THROUGH TESTS ====================

    public function test_has_one_through_multiple_returns_single_result(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $item = $department->firstItem;

        $this->assertNotNull($item);
        $this->assertInstanceOf(TestItemRelation::class, $item);
        $this->assertStringContainsString('Prod Item', $item->ITNAME);
    }

    public function test_has_one_through_multiple_eager_loading(): void
    {
        $departments = TestDepartment::unfiltered()
            ->with('firstItem')
            ->where('DPCOMP', '1')
            ->get();

        $this->assertCount(2, $departments);

        $dept1 = $departments->firstWhere('DPCODE', 'DEPT1');
        $this->assertNotNull($dept1->firstItem);
        $this->assertStringContainsString('Prod Item', $dept1->firstItem->ITNAME);
    }

    public function test_has_one_through_multiple_with_testing_scope(): void
    {
        $department = TestDepartment::testing()->unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $item = $department->firstItem;

        $this->assertNotNull($item);
        $this->assertStringContainsString('TEST Item', $item->ITNAME);
    }

    public function test_has_one_through_multiple_eager_loading_with_testing(): void
    {
        $departments = TestDepartment::testing()->unfiltered()
            ->with('firstItem')
            ->get();

        $this->assertNotEmpty($departments);

        foreach ($departments as $department) {
            if ($department->firstItem) {
                $this->assertStringContainsString('TEST', $department->firstItem->ITNAME);
            }
        }
    }

    // ==================== HAS MANY THROUGH TESTS ====================

    public function test_has_many_through_multiple_returns_collection(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $items = $department->items;

        $this->assertNotEmpty($items);
        // DEPT1 has CAT1 and CAT2, CAT1 has ITEM1 and ITEM2, CAT2 has ITEM3
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertStringContainsString('Prod Item', $item->ITNAME);
        }
    }

    public function test_has_many_through_multiple_eager_loading(): void
    {
        $departments = TestDepartment::unfiltered()
            ->with('items')
            ->where('DPCOMP', '1')
            ->get();

        $dept1 = $departments->firstWhere('DPCODE', 'DEPT1');
        $this->assertCount(3, $dept1->items);

        $dept2 = $departments->firstWhere('DPCODE', 'DEPT2');
        $this->assertCount(1, $dept2->items);
    }

    public function test_has_many_through_multiple_with_testing_scope(): void
    {
        $department = TestDepartment::testing()->unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $items = $department->items;

        $this->assertNotEmpty($items);
        // TEST DEPT1 has TEST CAT1 and CAT2, with 3 TEST items
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertStringContainsString('TEST Item', $item->ITNAME);
        }
    }

    public function test_has_many_through_multiple_eager_loading_with_testing(): void
    {
        $departments = TestDepartment::testing()->unfiltered()
            ->with('items')
            ->get();

        $this->assertNotEmpty($departments);

        foreach ($departments as $department) {
            foreach ($department->items as $item) {
                $this->assertStringContainsString('TEST', $item->ITNAME);
            }
        }
    }

    // ==================== COMPARISON WITH SINGLE COLUMN ====================

    public function test_single_column_has_one_through_works(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $item = $department->firstItemSingle;

        $this->assertNotNull($item);
        $this->assertStringContainsString('Prod Item', $item->ITNAME);
    }

    public function test_single_column_has_many_through_works(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $items = $department->itemsSingle;

        $this->assertNotEmpty($items);
    }

    // ==================== SQL VERIFICATION ====================

    public function test_has_one_through_generates_correct_sql(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $sql = $department->firstItem()->toSql();

        // Should join through the category table
        $this->assertStringContainsString('join', strtolower($sql));
        $this->assertStringContainsString('test_categories', $sql);
    }

    public function test_has_many_through_generates_correct_sql(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->where('DPCOMP', '1')
            ->first();

        $sql = $department->items()->toSql();

        // Should join through the category table
        $this->assertStringContainsString('join', strtolower($sql));
        $this->assertStringContainsString('test_categories', $sql);
    }

    public function test_testing_scope_uses_test_tables_in_through(): void
    {
        $department = TestDepartment::testing()->unfiltered()
            ->where('DPCODE', 'DEPT1')
            ->first();

        $sql = $department->items()->toSql();

        // Should use test schema tables (t60_ prefix)
        $this->assertStringContainsString('t60_test_categories', $sql);
        $this->assertStringContainsString('t60_test_items_rel', $sql);
    }

    // ==================== EDGE CASES ====================

    public function test_has_one_through_returns_null_when_no_match(): void
    {
        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'DEPT2')
            ->where('DPCOMP', '2') // Company 2, DEPT2 doesn't exist in company 2
            ->first();

        // DEPT2 only exists in company 1
        $this->assertNull($department);
    }

    public function test_has_many_through_returns_empty_when_no_items(): void
    {
        // Create a department with no categories
        $this->app['db']->connection('testing')->table('test_departments')->insert([
            ['DPCODE' => 'EMPTY', 'DPNAME' => 'Empty Dept', 'DPCOMP' => '1', 'DPDLTC' => 'A'],
        ]);

        $department = TestDepartment::unfiltered()
            ->where('DPCODE', 'EMPTY')
            ->where('DPCOMP', '1')
            ->first();

        $items = $department->items;

        $this->assertEmpty($items);
    }

    // ==================== WHERE HAS TESTS ====================

    public function test_where_has_through_relationship(): void
    {
        $departments = TestDepartment::unfiltered()
            ->whereHas('items')
            ->where('DPCOMP', '1')
            ->get();

        // Both DEPT1 and DEPT2 have items
        $this->assertCount(2, $departments);
    }

    public function test_where_has_through_relationship_with_testing(): void
    {
        $departments = TestDepartment::testing()->unfiltered()
            ->whereHas('items')
            ->get();

        // Only DEPT1 has items in test schema
        $this->assertCount(1, $departments);
        $this->assertEquals('DEPT1', $departments->first()->DPCODE);
    }
}
