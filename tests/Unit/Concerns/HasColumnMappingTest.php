<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

class HasColumnMappingTest extends TestCase
{
    public function test_get_maps_returns_column_mappings(): void
    {
        $model = new TestItem();

        $maps = $model->getMaps();

        $this->assertArrayHasKey('ICITEM', $maps);
        $this->assertEquals('item_number', $maps['ICITEM']);
    }

    public function test_has_maps_returns_true_when_maps_defined(): void
    {
        $model = new TestItem();

        $this->assertTrue($model->hasMaps());
    }

    public function test_get_reverse_maps_returns_human_to_db_mapping(): void
    {
        $model = new TestItem();

        $reverseMaps = $model->getReverseMaps();

        $this->assertArrayHasKey('item_number', $reverseMaps);
        $this->assertEquals('ICITEM', $reverseMaps['item_number']);
    }

    public function test_get_db_column_translates_mapped_name(): void
    {
        $model = new TestItem();

        $this->assertEquals('ICITEM', $model->getDbColumn('item_number'));
        $this->assertEquals('ICDESC', $model->getDbColumn('description'));
    }

    public function test_get_db_column_returns_original_when_not_mapped(): void
    {
        $model = new TestItem();

        $this->assertEquals('unknown_column', $model->getDbColumn('unknown_column'));
    }

    public function test_get_mapped_column_translates_db_name(): void
    {
        $model = new TestItem();

        $this->assertEquals('item_number', $model->getMappedColumn('ICITEM'));
        $this->assertEquals('description', $model->getMappedColumn('ICDESC'));
    }

    public function test_get_mapped_column_returns_original_when_not_mapped(): void
    {
        $model = new TestItem();

        $this->assertEquals('UNKNOWN', $model->getMappedColumn('UNKNOWN'));
    }

    public function test_set_attribute_with_mapped_name(): void
    {
        $model = new TestItem();

        $model->setAttribute('item_number', 'TEST123');

        // Should be stored under DB column name
        $this->assertEquals('TEST123', $model->getRawAttributes()['ICITEM']);
    }

    public function test_get_attribute_with_mapped_name(): void
    {
        $model = new TestItem();
        $model->setRawAttributes(['ICITEM' => 'TEST123']);

        $this->assertEquals('TEST123', $model->getAttribute('item_number'));
    }

    public function test_get_attribute_with_db_name(): void
    {
        $model = new TestItem();
        $model->setRawAttributes(['ICITEM' => 'TEST123']);

        $this->assertEquals('TEST123', $model->getAttribute('ICITEM'));
    }

    public function test_attributes_to_array_applies_mapping(): void
    {
        $model = new TestItem();
        $model->setRawAttributes([
            'ICITEM' => 'TEST123',
            'ICDESC' => 'Test Description',
            'ICCOMP' => '1',
        ]);

        $array = $model->attributesToArray();

        $this->assertArrayHasKey('item_number', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('company_number', $array);
        $this->assertEquals('TEST123', $array['item_number']);
    }

    public function test_to_array_uses_mapped_names(): void
    {
        $model = new TestItem();
        $model->setRawAttributes([
            'ICITEM' => 'TEST123',
            'ICDESC' => 'Test Description',
        ]);

        $array = $model->toArray();

        $this->assertArrayHasKey('item_number', $array);
        $this->assertArrayNotHasKey('ICITEM', $array);
    }

    public function test_get_raw_attributes_returns_unmapped(): void
    {
        $model = new TestItem();
        $model->setRawAttributes([
            'ICITEM' => 'TEST123',
            'ICDESC' => 'Test Description',
        ]);

        $raw = $model->getRawAttributes();

        $this->assertArrayHasKey('ICITEM', $raw);
        $this->assertArrayNotHasKey('item_number', $raw);
    }

    // ==================== SELECT SCOPE TESTS ====================

    public function test_select_mapped_generates_correct_columns(): void
    {
        $query = TestItem::unfiltered()->selectMapped();
        $sql = $query->toSql();

        // Should select the DB column names from $maps
        $this->assertStringContainsString('ICITEM', $sql);
        $this->assertStringContainsString('ICDESC', $sql);
        $this->assertStringContainsString('ICCOMP', $sql);
        $this->assertStringContainsString('ICDLTC', $sql);
    }

    public function test_select_all_selects_star(): void
    {
        $query = TestItem::unfiltered()->selectAll();
        $sql = $query->toSql();

        $this->assertStringContainsString('*', $sql);
    }

    public function test_select_all_bypasses_auto_select_mapped(): void
    {
        // selectAll should prevent autoSelectMapped from running
        $query = TestItem::selectAll();

        $columns = $query->getQuery()->columns;

        $this->assertEquals(['*'], $columns);
    }

    public function test_select_mapped_only_includes_mapped_columns(): void
    {
        $query = TestItem::unfiltered()->selectMapped();

        $columns = $query->getQuery()->columns;

        // Should only have the keys from $maps
        $this->assertContains('ICITEM', $columns);
        $this->assertContains('ICDESC', $columns);
        $this->assertContains('ICCOMP', $columns);
        $this->assertContains('ICDLTC', $columns);
        $this->assertContains('ICCOST', $columns);
        $this->assertContains('ICDATE', $columns);
    }
}
