<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Tests for CRUD operations using mapped column names.
 * Verifies that aliased/mapped columns work correctly with Eloquent operations.
 */
class HasColumnMappingCrudTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Seed initial test data
        $this->app['db']->connection('testing')->table('test_items')->insert([
            ['ICITEM' => 'ITEM1', 'ICDESC' => 'Original Description', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 100, 'ICDATE' => 20251217],
        ]);
    }

    // ==================== CREATE TESTS ====================

    public function test_create_with_mapped_column_names(): void
    {
        $item = TestItem::unfiltered()->create([
            'item_number' => 'NEW001',
            'description' => 'New Item',
            'company_number' => '1',
            'delete_code' => 'A',
            'cost' => 500,
        ]);

        $this->assertEquals('NEW001', $item->item_number);
        $this->assertEquals('New Item', $item->description);

        // Verify it's actually in the database with correct DB column names
        $raw = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'NEW001')
            ->first();

        $this->assertNotNull($raw);
        $this->assertEquals('New Item', $raw->ICDESC);
    }

    public function test_create_with_db_column_names(): void
    {
        $item = TestItem::unfiltered()->create([
            'ICITEM' => 'NEW002',
            'ICDESC' => 'Another Item',
            'ICCOMP' => '1',
            'ICDLTC' => 'A',
            'ICCOST' => 600,
        ]);

        $this->assertEquals('NEW002', $item->item_number);
        $this->assertEquals('Another Item', $item->description);
    }

    // ==================== READ TESTS ====================

    public function test_where_with_mapped_column_name(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();

        $this->assertNotNull($item);
        $this->assertEquals('ITEM1', $item->item_number);
    }

    public function test_where_with_db_column_name(): void
    {
        $item = TestItem::unfiltered()->where('ICITEM', 'ITEM1')->first();

        $this->assertNotNull($item);
        $this->assertEquals('ITEM1', $item->item_number);
    }

    public function test_where_in_with_mapped_column_name(): void
    {
        TestItem::unfiltered()->create([
            'item_number' => 'ITEM2',
            'description' => 'Second Item',
            'company_number' => '1',
            'delete_code' => 'A',
        ]);

        $items = TestItem::unfiltered()->whereIn('item_number', ['ITEM1', 'ITEM2'])->get();

        $this->assertCount(2, $items);
    }

    public function test_order_by_with_mapped_column_name(): void
    {
        TestItem::unfiltered()->create([
            'item_number' => 'AAA001',
            'description' => 'First Alphabetically',
            'company_number' => '1',
            'delete_code' => 'A',
        ]);

        $items = TestItem::unfiltered()->orderBy('item_number')->get();

        $this->assertEquals('AAA001', $items->first()->item_number);
    }

    public function test_pluck_with_mapped_column_name(): void
    {
        $descriptions = TestItem::unfiltered()->pluck('description', 'item_number');

        $this->assertArrayHasKey('ITEM1', $descriptions->toArray());
        $this->assertEquals('Original Description', $descriptions['ITEM1']);
    }

    public function test_select_with_mapped_column_names(): void
    {
        $item = TestItem::unfiltered()
            ->select(['item_number', 'description'])
            ->where('item_number', 'ITEM1')
            ->first();

        $this->assertEquals('ITEM1', $item->item_number);
        $this->assertEquals('Original Description', $item->description);
    }

    // ==================== UPDATE TESTS ====================

    public function test_update_via_model_save_with_mapped_name(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $item->description = 'Updated Description';
        $item->save();

        // Reload and verify
        $fresh = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $this->assertEquals('Updated Description', $fresh->description);

        // Verify raw DB
        $raw = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'ITEM1')
            ->first();
        $this->assertEquals('Updated Description', $raw->ICDESC);
    }

    public function test_update_via_model_update_method(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $item->update(['description' => 'Method Updated']);

        $fresh = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $this->assertEquals('Method Updated', $fresh->description);
    }

    public function test_bulk_update_with_mapped_column_names(): void
    {
        TestItem::unfiltered()
            ->where('item_number', 'ITEM1')
            ->update(['description' => 'Bulk Updated']);

        $raw = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertEquals('Bulk Updated', $raw->ICDESC);
    }

    public function test_update_multiple_mapped_columns(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $item->description = 'Multi Update';
        $item->cost = 999;
        $item->save();

        $raw = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'ITEM1')
            ->first();

        $this->assertEquals('Multi Update', $raw->ICDESC);
        $this->assertEquals(999, $raw->ICCOST);
    }

    // ==================== DELETE TESTS ====================

    public function test_delete_model(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $item->delete();

        $count = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'ITEM1')
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_bulk_delete_with_mapped_column(): void
    {
        TestItem::unfiltered()->create([
            'item_number' => 'DELETE1',
            'description' => 'To Delete',
            'company_number' => '1',
            'delete_code' => 'D',
        ]);

        TestItem::unfiltered()->where('delete_code', 'D')->delete();

        $count = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICDLTC', 'D')
            ->count();

        $this->assertEquals(0, $count);
    }

    // ==================== INSERT TESTS ====================

    public function test_insert_with_mapped_columns(): void
    {
        TestItem::unfiltered()->insert([
            'item_number' => 'INSERT1',
            'description' => 'Inserted Item',
            'company_number' => '1',
            'delete_code' => 'A',
        ]);

        $raw = $this->app['db']->connection('testing')
            ->table('test_items')
            ->where('ICITEM', 'INSERT1')
            ->first();

        $this->assertNotNull($raw);
        $this->assertEquals('Inserted Item', $raw->ICDESC);
    }

    public function test_batch_insert_with_mapped_columns(): void
    {
        TestItem::unfiltered()->insert([
            ['item_number' => 'BATCH1', 'description' => 'Batch 1', 'company_number' => '1', 'delete_code' => 'A'],
            ['item_number' => 'BATCH2', 'description' => 'Batch 2', 'company_number' => '1', 'delete_code' => 'A'],
        ]);

        $count = $this->app['db']->connection('testing')
            ->table('test_items')
            ->whereIn('ICITEM', ['BATCH1', 'BATCH2'])
            ->count();

        $this->assertEquals(2, $count);
    }

    // ==================== ATTRIBUTE ACCESS TESTS ====================

    public function test_get_attribute_by_mapped_name(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();

        $this->assertEquals('ITEM1', $item->item_number);
        $this->assertEquals('Original Description', $item->description);
    }

    public function test_get_attribute_by_db_name(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();

        $this->assertEquals('ITEM1', $item->ICITEM);
        $this->assertEquals('Original Description', $item->ICDESC);
    }

    public function test_set_attribute_by_mapped_name(): void
    {
        $item = new TestItem();
        $item->item_number = 'TEST123';

        // Should be stored under DB column name internally
        $this->assertEquals('TEST123', $item->getRawAttributes()['ICITEM']);
    }

    public function test_to_array_uses_mapped_names(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $array = $item->toArray();

        $this->assertArrayHasKey('item_number', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayNotHasKey('ICITEM', $array);
        $this->assertArrayNotHasKey('ICDESC', $array);
    }

    public function test_to_json_uses_mapped_names(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM1')->first();
        $json = $item->toJson();

        $this->assertStringContainsString('item_number', $json);
        $this->assertStringContainsString('description', $json);
        $this->assertStringNotContainsString('ICITEM', $json);
        $this->assertStringNotContainsString('ICDESC', $json);
    }
}
