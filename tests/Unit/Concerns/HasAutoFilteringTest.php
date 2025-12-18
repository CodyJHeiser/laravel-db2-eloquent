<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

class HasAutoFilteringTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfNoDatabaseDriver();

        // Seed test data
        $this->app['db']->connection('testing')->table('test_items')->insert([
            ['ICITEM' => 'ITEM1', 'ICDESC' => 'Active Item 1', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 100, 'ICDATE' => 20251217],
            ['ICITEM' => 'ITEM2', 'ICDESC' => 'Active Item 2', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 200, 'ICDATE' => 20251218],
            ['ICITEM' => 'ITEM3', 'ICDESC' => 'Deleted Item', 'ICCOMP' => '1', 'ICDLTC' => 'D', 'ICCOST' => 300, 'ICDATE' => 20251219],
            ['ICITEM' => 'ITEM4', 'ICDESC' => 'Company 2 Item', 'ICCOMP' => '2', 'ICDLTC' => 'A', 'ICCOST' => 400, 'ICDATE' => 20251220],
        ]);
    }

    public function test_default_query_filters_by_active_and_company(): void
    {
        $items = TestItem::all();

        $this->assertCount(2, $items);
        $this->assertTrue($items->pluck('item_number')->contains('ITEM1'));
        $this->assertTrue($items->pluck('item_number')->contains('ITEM2'));
        $this->assertFalse($items->pluck('item_number')->contains('ITEM3')); // Deleted
        $this->assertFalse($items->pluck('item_number')->contains('ITEM4')); // Company 2
    }

    public function test_with_inactive_includes_deleted_records(): void
    {
        $items = TestItem::withInactive()->get();

        $this->assertCount(3, $items); // 3 from company 1 (including deleted)
        $this->assertTrue($items->pluck('item_number')->contains('ITEM3'));
    }

    public function test_with_all_companies_includes_all_companies(): void
    {
        $items = TestItem::withAllCompanies()->get();

        $this->assertCount(3, $items); // 3 active items (company 1 + company 2)
        $this->assertTrue($items->pluck('item_number')->contains('ITEM4'));
    }

    public function test_for_company_filters_specific_company(): void
    {
        $items = TestItem::forCompany('2')->get();

        $this->assertCount(1, $items);
        $this->assertEquals('ITEM4', $items->first()->item_number);
    }

    public function test_unfiltered_removes_all_filters(): void
    {
        $items = TestItem::unfiltered()->get();

        $this->assertCount(4, $items); // All items
    }

    public function test_filters_can_be_combined(): void
    {
        $items = TestItem::withInactive()->withAllCompanies()->get();

        $this->assertCount(4, $items); // All items, no filters
    }

    public function test_where_clause_works_with_filters(): void
    {
        $item = TestItem::where('item_number', 'ITEM1')->first();

        $this->assertNotNull($item);
        $this->assertEquals('ITEM1', $item->item_number);
    }

    public function test_filtered_query_excludes_deleted_items(): void
    {
        $item = TestItem::where('item_number', 'ITEM3')->first();

        $this->assertNull($item); // Should not find deleted item
    }

    public function test_unfiltered_can_find_deleted_items(): void
    {
        $item = TestItem::unfiltered()->where('item_number', 'ITEM3')->first();

        $this->assertNotNull($item);
        $this->assertEquals('ITEM3', $item->item_number);
    }
}
