<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItemWithExtension;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Tests for the HasExtensions trait.
 */
class HasExtensionsTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Seed test data
        $this->app['db']->connection('testing')->table('test_items')->insert([
            ['ICITEM' => 'ITEM1', 'ICDESC' => 'Test Item 1', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 100, 'ICDATE' => 20251217],
            ['ICITEM' => 'ITEM2', 'ICDESC' => 'Test Item 2', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 200, 'ICDATE' => 20251217],
            ['ICITEM' => 'ITEM3', 'ICDESC' => 'Test Item 3', 'ICCOMP' => '2', 'ICDLTC' => 'A', 'ICCOST' => 300, 'ICDATE' => 20251217],
        ]);

        // Extension data - ITEM1 has extension, ITEM2 doesn't, ITEM3 has extension
        $this->app['db']->connection('testing')->table('test_item_extensions')->insert([
            ['EXITEM' => 'ITEM1', 'EXCOMP' => '1', 'EXDATA' => 'Extension Data 1', 'EXNOTE' => 'Note 1'],
            ['EXITEM' => 'ITEM3', 'EXCOMP' => '2', 'EXDATA' => 'Extension Data 3', 'EXNOTE' => 'Note 3'],
        ]);

        // Detail data - ITEM1 has multiple details, ITEM2 has one, ITEM3 has none
        $this->app['db']->connection('testing')->table('test_item_details')->insert([
            ['DTITEM' => 'ITEM1', 'DTINFO' => 'Detail Info 1a'],
            ['DTITEM' => 'ITEM1', 'DTINFO' => 'Detail Info 1b'],
            ['DTITEM' => 'ITEM2', 'DTINFO' => 'Detail Info 2'],
        ]);
    }

    // ==================== HAS EXTENSIONS TESTS ====================

    public function test_has_extensions_returns_true_when_extensions_defined(): void
    {
        $model = new TestItemWithExtension();

        $this->assertTrue($model->hasExtensions());
    }

    public function test_has_extensions_returns_false_when_no_extensions(): void
    {
        $model = new TestItem();

        $this->assertFalse($model->hasExtensions());
    }

    // ==================== WITH EXTENSIONS SCOPE TESTS ====================

    public function test_with_extensions_returns_builder(): void
    {
        $query = TestItemWithExtension::unfiltered()->withExtensions();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function test_with_extensions_joins_all_extension_tables(): void
    {
        $sql = TestItemWithExtension::unfiltered()->withExtensions()->toSql();

        $this->assertStringContainsString('left join', strtolower($sql));
        $this->assertStringContainsString('test_item_extensions', $sql);
        $this->assertStringContainsString('test_item_details', $sql);
    }

    public function test_with_extensions_can_filter_specific_tables(): void
    {
        $sql = TestItemWithExtension::unfiltered()
            ->withExtensions(['test_item_extensions'])
            ->toSql();

        $this->assertStringContainsString('test_item_extensions', $sql);
        $this->assertStringNotContainsString('test_item_details', $sql);
    }

    public function test_with_extensions_returns_extension_data(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->withExtensions(['test_item_extensions'])
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals('Extension Data 1', $item->EXDATA);
    }

    public function test_with_extensions_returns_null_for_missing_extension(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->withExtensions(['test_item_extensions'])
            ->where('ICITEM', 'ITEM2')
            ->first();

        $this->assertNotNull($item);
        $this->assertNull($item->EXDATA);
    }

    // ==================== WITH EXTENSION SCOPE TESTS ====================

    public function test_with_extension_joins_single_table(): void
    {
        $sql = TestItemWithExtension::unfiltered()
            ->withExtension('test_item_extensions')
            ->toSql();

        $this->assertStringContainsString('test_item_extensions', $sql);
        $this->assertStringNotContainsString('test_item_details', $sql);
    }

    public function test_with_extension_can_chain_with_queries(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->withExtension('test_item_extensions')
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals('ITEM1', $item->ICITEM);
    }

    // ==================== WHERE HAS EXTENSION SCOPE TESTS ====================

    public function test_where_has_extension_filters_to_items_with_extension(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_extensions')
            ->get();

        // ITEM1 and ITEM3 have extensions
        $this->assertCount(2, $items);
        $itemNumbers = $items->pluck('ICITEM')->toArray();
        $this->assertContains('ITEM1', $itemNumbers);
        $this->assertContains('ITEM3', $itemNumbers);
        $this->assertNotContains('ITEM2', $itemNumbers);
    }

    public function test_where_has_extension_with_count_operator(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_details', '>', 1)
            ->get();

        // Only ITEM1 has more than 1 detail
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM1', $items->first()->ICITEM);
    }

    public function test_where_has_extension_with_exact_count(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_details', '=', 1)
            ->get();

        // Only ITEM2 has exactly 1 detail
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM2', $items->first()->ICITEM);
    }

    // ==================== WHERE DOESNT HAVE EXTENSION SCOPE TESTS ====================

    public function test_where_doesnt_have_extension_filters_items_without(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereDoesntHaveExtension('test_item_extensions')
            ->get();

        // Only ITEM2 has no extension
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM2', $items->first()->ICITEM);
    }

    public function test_where_doesnt_have_extension_for_details(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereDoesntHaveExtension('test_item_details')
            ->get();

        // Only ITEM3 has no details
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM3', $items->first()->ICITEM);
    }

    // ==================== WITH WHERE HAS EXTENSION SCOPE TESTS ====================

    public function test_with_where_has_extension_joins_and_filters(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->withWhereHasExtension('test_item_extensions')
            ->get();

        // Should only have items with extensions and include extension data
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertNotNull($item->EXDATA);
        }
    }

    public function test_with_where_has_extension_with_count_operator(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->withWhereHasExtension('test_item_details', '>', 1)
            ->get();

        // Only ITEM1 has more than 1 detail, and should have detail data joined
        $this->assertCount(2, $items); // 2 rows due to join expansion
        foreach ($items as $item) {
            $this->assertEquals('ITEM1', $item->ICITEM);
            $this->assertNotNull($item->DTINFO);
        }
    }

    // ==================== LOAD EXTENSION TESTS ====================

    public function test_load_extension_retrieves_extension_data(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $extension = $item->loadExtension('test_item_extensions');

        $this->assertNotNull($extension);
        $this->assertEquals('Extension Data 1', $extension->EXDATA);
    }

    public function test_load_extension_returns_null_for_missing_data(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM2')
            ->first();

        $extension = $item->loadExtension('test_item_extensions');

        $this->assertNull($extension);
    }

    public function test_load_extension_returns_null_for_unknown_table(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $extension = $item->loadExtension('nonexistent_table');

        $this->assertNull($extension);
    }

    // ==================== GET EXTENSION DATA TESTS ====================

    public function test_get_extension_data_returns_loaded_data(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $item->loadExtension('test_item_extensions');
        $data = $item->getExtensionData('test_item_extensions');

        $this->assertNotNull($data);
        $this->assertEquals('Extension Data 1', $data->EXDATA);
    }

    public function test_get_extension_data_returns_null_when_not_loaded(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $data = $item->getExtensionData('test_item_extensions');

        $this->assertNull($data);
    }

    // ==================== COUNT EXTENSION RECORDS TESTS ====================

    public function test_count_extension_records_returns_correct_count(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $count = $item->countExtensionRecords('test_item_details');

        $this->assertEquals(2, $count);
    }

    public function test_count_extension_records_returns_zero_for_no_records(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM3')
            ->first();

        $count = $item->countExtensionRecords('test_item_details');

        $this->assertEquals(0, $count);
    }

    public function test_count_extension_records_returns_zero_for_unknown_table(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $count = $item->countExtensionRecords('nonexistent_table');

        $this->assertEquals(0, $count);
    }

    // ==================== HAS EXTENSION RECORDS TESTS ====================

    public function test_has_extension_records_returns_true_when_exists(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertTrue($item->hasExtensionRecords('test_item_extensions'));
    }

    public function test_has_extension_records_returns_false_when_none(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM2')
            ->first();

        $this->assertFalse($item->hasExtensionRecords('test_item_extensions'));
    }

    // ==================== HAS MULTIPLE EXTENSION RECORDS TESTS ====================

    public function test_has_multiple_extension_records_returns_true(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertTrue($item->hasMultipleExtensionRecords('test_item_details'));
    }

    public function test_has_multiple_extension_records_returns_false_for_one(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM2')
            ->first();

        $this->assertFalse($item->hasMultipleExtensionRecords('test_item_details'));
    }

    public function test_has_multiple_extension_records_returns_false_for_zero(): void
    {
        $item = TestItemWithExtension::unfiltered()
            ->where('ICITEM', 'ITEM3')
            ->first();

        $this->assertFalse($item->hasMultipleExtensionRecords('test_item_details'));
    }

    // ==================== CHAINING TESTS ====================

    public function test_can_chain_multiple_extension_scopes(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_extensions')
            ->whereHasExtension('test_item_details')
            ->get();

        // Only ITEM1 has both extensions and details
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM1', $items->first()->ICITEM);
    }

    public function test_can_chain_with_regular_where_clauses(): void
    {
        $items = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_extensions')
            ->where('ICCOMP', '1')
            ->get();

        // Only ITEM1 in company 1 has extension
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM1', $items->first()->ICITEM);
    }

    // ==================== SQL GENERATION TESTS ====================

    public function test_with_extensions_generates_correct_join_conditions(): void
    {
        $sql = TestItemWithExtension::unfiltered()
            ->withExtension('test_item_extensions')
            ->toSql();

        // Check join conditions are present
        $this->assertStringContainsString('EXITEM', $sql);
        $this->assertStringContainsString('ICITEM', $sql);
        $this->assertStringContainsString('EXCOMP', $sql);
        $this->assertStringContainsString('ICCOMP', $sql);
    }

    public function test_where_has_extension_generates_subquery(): void
    {
        $sql = TestItemWithExtension::unfiltered()
            ->whereHasExtension('test_item_extensions')
            ->toSql();

        // Should contain a COUNT subquery
        $this->assertStringContainsString('COUNT', strtoupper($sql));
    }
}
