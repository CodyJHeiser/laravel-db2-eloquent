<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Relations;

use CodyJHeiser\Db2Eloquent\Relations\BelongsToManyMultiple;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItemRelation;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestTag;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Tests for BelongsToManyMultiple relation.
 * Verifies multi-column belongsToMany relationships work correctly with DB2-compatible SQL.
 */
class BelongsToManyMultipleTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Seed items
        $this->app['db']->connection('testing')->table('test_items_rel')->insert([
            ['ITCODE' => 'ITEM1', 'ITNAME' => 'Item 1', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM2', 'ITNAME' => 'Item 2', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM3', 'ITNAME' => 'Item 3', 'ITCAT' => 'CAT2', 'ITCOMP' => '1', 'ITDLTC' => 'A'],
            ['ITCODE' => 'ITEM4', 'ITNAME' => 'Item 4 Co2', 'ITCAT' => 'CAT1', 'ITCOMP' => '2', 'ITDLTC' => 'A'],
        ]);

        // Seed tags
        $this->app['db']->connection('testing')->table('test_tags')->insert([
            ['TGCODE' => 'TAG1', 'TGNAME' => 'Red', 'TGCOMP' => '1', 'TGDLTC' => 'A'],
            ['TGCODE' => 'TAG2', 'TGNAME' => 'Blue', 'TGCOMP' => '1', 'TGDLTC' => 'A'],
            ['TGCODE' => 'TAG3', 'TGNAME' => 'Green', 'TGCOMP' => '1', 'TGDLTC' => 'A'],
            ['TGCODE' => 'TAG1', 'TGNAME' => 'Red Co2', 'TGCOMP' => '2', 'TGDLTC' => 'A'],
        ]);

        // Seed pivot:
        //   ITEM1 (co1) -> TAG1 (co1), TAG2 (co1)
        //   ITEM2 (co1) -> TAG1 (co1)
        //   ITEM3 (co1) -> TAG3 (co1)
        //   ITEM4 (co2) -> TAG1 (co2)  -- different company, tests composite key isolation
        $this->app['db']->connection('testing')->table('test_item_tag')->insert([
            ['PTITEM' => 'ITEM1', 'PTITCOMP' => '1', 'PTTAG' => 'TAG1', 'PTTAGCOMP' => '1'],
            ['PTITEM' => 'ITEM1', 'PTITCOMP' => '1', 'PTTAG' => 'TAG2', 'PTTAGCOMP' => '1'],
            ['PTITEM' => 'ITEM2', 'PTITCOMP' => '1', 'PTTAG' => 'TAG1', 'PTTAGCOMP' => '1'],
            ['PTITEM' => 'ITEM3', 'PTITCOMP' => '1', 'PTTAG' => 'TAG3', 'PTTAGCOMP' => '1'],
            ['PTITEM' => 'ITEM4', 'PTITCOMP' => '2', 'PTTAG' => 'TAG1', 'PTTAGCOMP' => '2'],
        ]);
    }

    // ==================== BASIC RELATION TESTS ====================

    public function test_returns_belongs_to_many_multiple_instance(): void
    {
        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEM1')->first();

        $this->assertInstanceOf(BelongsToManyMultiple::class, $item->tags());
    }

    public function test_get_returns_related_models(): void
    {
        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEM1')->first();

        $tags = $item->tags;

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->contains('TGCODE', 'TAG1'));
        $this->assertTrue($tags->contains('TGCODE', 'TAG2'));
    }

    public function test_get_returns_correct_results_with_composite_keys(): void
    {
        // ITEM3 has one tag (TAG3) in company 1
        $item = TestItemRelation::unfiltered()
            ->where('ITCODE', 'ITEM3')
            ->where('ITCOMP', '1')
            ->first();

        $tags = $item->tags;

        $this->assertCount(1, $tags);
        $this->assertEquals('TAG3', $tags->first()->TGCODE);
        $this->assertEquals('1', $tags->first()->TGCOMP);
    }

    public function test_composite_keys_isolate_across_companies(): void
    {
        // ITEM1 exists in company 1 with 2 tags, ITEM4 exists in company 2 with 1 tag
        // Verify the pivot's composite keys correctly isolate them
        $item1 = TestItemRelation::unfiltered()
            ->where('ITCODE', 'ITEM1')
            ->where('ITCOMP', '1')
            ->first();

        $item4 = TestItemRelation::unfiltered()
            ->where('ITCODE', 'ITEM4')
            ->where('ITCOMP', '2')
            ->first();

        $this->assertCount(2, $item1->tags);

        // ITEM4's tags are in company 2, but auto-filtering on Tag model filters to company 1
        // So with auto-filtering, ITEM4 gets 0 tags (expected behavior)
        $this->assertCount(0, $item4->tags);
    }

    public function test_get_returns_empty_collection_when_no_pivot_rows(): void
    {
        // Create an item with no tags
        $this->app['db']->connection('testing')->table('test_items_rel')->insert([
            'ITCODE' => 'ITEMX', 'ITNAME' => 'No Tags', 'ITCAT' => 'CAT1', 'ITCOMP' => '1', 'ITDLTC' => 'A',
        ]);

        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEMX')->first();

        $tags = $item->tags;

        $this->assertCount(0, $tags);
    }

    // ==================== INVERSE RELATION TESTS ====================

    public function test_inverse_relation_returns_related_models(): void
    {
        $tag = TestTag::unfiltered()->where('TGCODE', 'TAG1')->where('TGCOMP', '1')->first();

        $items = $tag->items;

        $this->assertCount(2, $items);
        $this->assertTrue($items->contains('ITCODE', 'ITEM1'));
        $this->assertTrue($items->contains('ITCODE', 'ITEM2'));
    }

    // ==================== EAGER LOADING TESTS ====================

    public function test_eager_loading_works(): void
    {
        $items = TestItemRelation::unfiltered()
            ->with('tags')
            ->whereIn('ITCODE', ['ITEM1', 'ITEM2', 'ITEM3'])
            ->where('ITCOMP', '1')
            ->get();

        $item1 = $items->firstWhere('ITCODE', 'ITEM1');
        $this->assertCount(2, $item1->tags);

        $item2 = $items->firstWhere('ITCODE', 'ITEM2');
        $this->assertCount(1, $item2->tags);

        $item3 = $items->firstWhere('ITCODE', 'ITEM3');
        $this->assertCount(1, $item3->tags);
    }

    public function test_eager_loading_inverse(): void
    {
        $tags = TestTag::unfiltered()
            ->with('items')
            ->whereIn('TGCODE', ['TAG1', 'TAG2', 'TAG3'])
            ->get();

        $tag1 = $tags->firstWhere('TGCODE', 'TAG1');
        $this->assertCount(2, $tag1->items);

        $tag2 = $tags->firstWhere('TGCODE', 'TAG2');
        $this->assertCount(1, $tag2->items);
    }

    // ==================== WHERE HAS TESTS ====================

    public function test_where_has_filters_by_relation(): void
    {
        $items = TestItemRelation::unfiltered()
            ->whereHas('tags')
            ->where('ITCOMP', '1')
            ->get();

        // ITEM1, ITEM2, ITEM3 all have tags in company 1
        $this->assertCount(3, $items);
    }

    public function test_where_has_with_callback(): void
    {
        $items = TestItemRelation::unfiltered()
            ->whereHas('tags', function ($query) {
                $query->where('test_tags.TGCODE', 'TAG2');
            })
            ->get();

        // Only ITEM1 has TAG2
        $this->assertCount(1, $items);
        $this->assertEquals('ITEM1', $items->first()->ITCODE);
    }

    // ==================== SQL VERIFICATION ====================

    public function test_generates_join_in_sql(): void
    {
        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEM1')->first();

        $sql = $item->tags()->toSql();

        // Should join through the pivot table
        $this->assertStringContainsString('join', strtolower($sql));
        $this->assertStringContainsString('test_item_tag', $sql);
    }

    // ==================== MAPPED COLUMN NAME TESTS ====================

    public function test_mapped_column_names_work(): void
    {
        $item = TestItemRelation::unfiltered()->where('ITCODE', 'ITEM1')->first();

        // tagsMapped() uses mapped names for parentKey/relatedKey
        $tags = $item->tagsMapped;

        $this->assertCount(2, $tags);
    }
}
