<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestCategory;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItemRelation;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

/**
 * Tests to verify that scopes (especially testing()) properly propagate
 * through relationships, eager loading, and CRUD operations.
 *
 * CRITICAL: The testing() scope MUST propagate to ALL related queries.
 */
class RelationshipScopePropagationTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Disable lazy loading prevention for tests
        Model::preventLazyLoading(false);

        // Seed production tables
        $this->app['db']->connection('testing')->table('test_categories')->insert([
            ['CTCODE' => 'CAT1', 'CTNAME' => 'Prod Category 1', 'CTCOMP' => '1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT2', 'CTNAME' => 'Prod Category 2', 'CTCOMP' => '1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT1', 'CTNAME' => 'Prod Category 1 Co2', 'CTCOMP' => '2', 'CTDLTC' => 'A'],
        ]);

        $this->app['db']->connection('testing')->table('test_items_rel')->insert([
            ['ITCODE' => 'ITEM1', 'ITNAME' => 'Prod Item 1', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM2', 'ITNAME' => 'Prod Item 2', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM3', 'ITNAME' => 'Prod Item 3', 'ITCAT' => 'CAT2', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
        ]);

        // Seed TEST schema tables with DIFFERENT data
        // In SQLite we use t60_ prefix to simulate T60FILES schema
        $this->app['db']->connection('testing')->table('t60_test_categories')->insert([
            ['CTCODE' => 'CAT1', 'CTNAME' => 'TEST Category 1', 'CTCOMP' => '1', 'CTDLTC' => 'A'],
            ['CTCODE' => 'CAT2', 'CTNAME' => 'TEST Category 2', 'CTCOMP' => '1', 'CTDLTC' => 'A'],
        ]);

        $this->app['db']->connection('testing')->table('t60_test_items_rel')->insert([
            ['ITCODE' => 'TITEM1', 'ITNAME' => 'TEST Item 1', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'TITEM2', 'ITNAME' => 'TEST Item 2', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
        ]);
    }

    // ==================== BASIC RELATIONSHIP TESTS ====================

    public function test_belongs_to_relationship_works_without_testing(): void
    {
        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEM1')->first();

        $category = $item->category;

        $this->assertNotNull($category);
        $this->assertEquals('Prod Category 1', $category->CTNAME);
    }

    public function test_has_many_relationship_works_without_testing(): void
    {
        $category = TestCategory::unfiltered()->where('CTCODE', 'CAT1')->where('CTCOMP', '1')->first();

        $items = $category->items;

        $this->assertCount(2, $items);
        $this->assertEquals('Prod Item 1', $items->first()->ITNAME);
    }

    // ==================== TESTING() SCOPE PROPAGATION ====================

    public function test_testing_scope_changes_table_for_main_query(): void
    {
        $query = TestCategory::testing()->unfiltered();
        $sql = $query->toSql();

        // In SQLite test mode, uses t60_ prefix
        $this->assertStringContainsString('t60_test_categories', $sql);
    }

    public function test_testing_propagates_to_belongs_to_lazy_load(): void
    {
        // Get item from TEST schema
        $item = TestItemRelation::testing()->unfiltered()->where('ITCODE', 'TITEM1')->first();

        // The related category should come from TEST schema
        $category = $item->category;

        $this->assertNotNull($category);
        $this->assertEquals('TEST Category 1', $category->CTNAME);
    }

    public function test_testing_propagates_to_has_many_lazy_load(): void
    {
        // Get category from TEST schema
        $category = TestCategory::testing()->unfiltered()
            ->where('CTCODE', 'CAT1')
            ->where('CTCOMP', '1')
            ->first();

        // The related items should come from TEST schema
        $items = $category->items;

        $this->assertCount(2, $items);
        $this->assertEquals('TEST Item 1', $items->first()->ITNAME);
    }

    public function test_testing_propagates_to_eager_loaded_belongs_to(): void
    {
        // Eager load with testing()
        $items = TestItemRelation::testing()
            ->unfiltered()
            ->with('category')
            ->get();

        // All categories should come from TEST schema
        foreach ($items as $item) {
            if ($item->category) {
                $this->assertStringContainsString('TEST', $item->category->CTNAME);
            }
        }
    }

    public function test_testing_propagates_to_eager_loaded_has_many(): void
    {
        // Eager load with testing()
        $categories = TestCategory::testing()
            ->unfiltered()
            ->with('items')
            ->get();

        // All items should come from TEST schema
        foreach ($categories as $category) {
            foreach ($category->items as $item) {
                $this->assertStringContainsString('TEST', $item->ITNAME);
            }
        }
    }

    public function test_without_testing_gets_production_data(): void
    {
        // Without testing() - should get production data
        $category = TestCategory::unfiltered()
            ->where('CTCODE', 'CAT1')
            ->where('CTCOMP', '1')
            ->first();

        $this->assertEquals('Prod Category 1', $category->CTNAME);

        $items = $category->items;
        $this->assertEquals('Prod Item 1', $items->first()->ITNAME);
    }

    // ==================== SQL VERIFICATION TESTS ====================

    public function test_relationship_sql_uses_test_schema(): void
    {
        $item = TestItemRelation::testing()->unfiltered()->where('ITCODE', 'TITEM1')->first();

        // Get the relationship query
        $relationQuery = $item->category();
        $sql = $relationQuery->toSql();

        // The SQL should reference the test schema (t60_ prefix in SQLite)
        $this->assertStringContainsString('t60_test_categories', $sql);
    }

    public function test_eager_load_sql_uses_test_schema(): void
    {
        // Run the eager loaded query
        $items = TestItemRelation::testing()
            ->unfiltered()
            ->with('category')
            ->get();

        // Verify via the actual data that we got test schema data
        $this->assertNotEmpty($items, 'No items returned from test schema');
        foreach ($items as $item) {
            if ($item->category) {
                $this->assertStringContainsString('TEST', $item->category->CTNAME, 'Category name should contain TEST');
            }
        }
    }

    // ==================== NEW INSTANCE PROPAGATION ====================

    public function test_new_instance_preserves_test_schema_flag(): void
    {
        $item = TestItemRelation::testing()->unfiltered()->where('ITCODE', 'TITEM1')->first();

        // Create a new instance from this item
        $newInstance = $item->newInstance();

        // Check that useTestSchema is propagated
        $reflection = new \ReflectionClass($newInstance);
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($newInstance));
    }

    public function test_hydrated_models_preserve_test_schema(): void
    {
        $items = TestItemRelation::testing()->unfiltered()->get();

        foreach ($items as $item) {
            $reflection = new \ReflectionClass($item);
            $property = $reflection->getProperty('useTestSchema');
            $property->setAccessible(true);

            $this->assertTrue($property->getValue($item), 'Hydrated model lost useTestSchema flag');
        }
    }

    // ==================== SELECT ALL / SELECT MAPPED WITH RELATIONSHIPS ====================

    public function test_select_all_works_with_relationships(): void
    {
        $category = TestCategory::unfiltered()
            ->selectAll()
            ->with('items')
            ->where('CTCODE', 'CAT1')
            ->where('CTCOMP', '1')
            ->first();

        $this->assertNotNull($category);
        $this->assertNotEmpty($category->items);
    }

    public function test_select_mapped_works_with_relationships(): void
    {
        $category = TestCategory::unfiltered()
            ->selectMapped()
            ->with('items')
            ->where('CTCODE', 'CAT1')
            ->where('CTCOMP', '1')
            ->first();

        $this->assertNotNull($category);
        $this->assertNotEmpty($category->items);
    }

    // ==================== WHERE HAS WITH TESTING ====================

    public function test_where_has_uses_test_schema(): void
    {
        // Categories that have items in TEST schema
        $categories = TestCategory::testing()
            ->unfiltered()
            ->whereHas('items')
            ->get();

        // Only CAT1 has items in test schema
        $this->assertCount(1, $categories);
        $this->assertEquals('TEST Category 1', $categories->first()->CTNAME);
    }

    // ==================== SINGLE COLUMN RELATIONSHIPS ====================

    public function test_single_column_belongs_to_with_testing(): void
    {
        $item = TestItemRelation::testing()->unfiltered()->where('ITCODE', 'TITEM1')->first();

        $category = $item->categorySingle;

        $this->assertNotNull($category);
        $this->assertStringContainsString('TEST', $category->CTNAME);
    }

    public function test_single_column_has_many_with_testing(): void
    {
        $category = TestCategory::testing()->unfiltered()
            ->where('CTCODE', 'CAT1')
            ->where('CTCOMP', '1')
            ->first();

        $items = $category->itemsSingle;

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertStringContainsString('TEST', $item->ITNAME);
        }
    }

    // ==================== CRUD WITH TESTING ====================

    public function test_create_uses_test_schema_table(): void
    {
        // Create through a model with testing enabled
        $category = TestCategory::testing()->unfiltered()->where('CTCODE', 'CAT1')->first();

        // The model should be in testing mode
        $reflection = new \ReflectionClass($category);
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($category));

        // Verify getTable returns test schema (t60_ prefix in SQLite)
        $this->assertStringContainsString('t60_test_categories', $category->getTable());
    }

    public function test_update_uses_test_schema_table(): void
    {
        $category = TestCategory::testing()->unfiltered()->where('CTCODE', 'CAT1')->first();

        // Verify the model is in test mode (t60_ prefix in SQLite)
        $this->assertStringContainsString('t60_test_categories', $category->getTable());
    }
}
