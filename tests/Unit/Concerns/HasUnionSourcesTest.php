<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Builders\ReadOnlyMappedBuilder;
use CodyJHeiser\Db2Eloquent\Exceptions\ReadOnlyModelException;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestUnionSource1;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestUnionSource2;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestUnionModel;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

class HasUnionSourcesTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Create union source tables
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_source_1', function ($table) {
            $table->string('SRCOMP', 2)->default('1');
            $table->string('SRNAME', 50)->nullable();
            $table->string('SRCODE', 10);
            $table->string('SRDLTC', 1)->default('A');
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_source_2', function ($table) {
            $table->string('SRCOMP', 2)->default('1');
            $table->string('SRNAME', 50)->nullable();
            $table->string('SRCODE', 10);
            $table->string('SRDLTC', 1)->default('A');
        });

        // Seed data
        $this->app['db']->connection('testing')->table('test_source_1')->insert([
            ['SRCOMP' => '1', 'SRNAME' => 'Live Record 1', 'SRCODE' => 'L001', 'SRDLTC' => 'A'],
            ['SRCOMP' => '1', 'SRNAME' => 'Live Record 2', 'SRCODE' => 'L002', 'SRDLTC' => 'A'],
            ['SRCOMP' => '1', 'SRNAME' => 'Live Deleted',  'SRCODE' => 'L003', 'SRDLTC' => 'D'],
            ['SRCOMP' => '2', 'SRNAME' => 'Live Company 2', 'SRCODE' => 'L004', 'SRDLTC' => 'A'],
        ]);

        $this->app['db']->connection('testing')->table('test_source_2')->insert([
            ['SRCOMP' => '1', 'SRNAME' => 'History Record 1', 'SRCODE' => 'H001', 'SRDLTC' => 'A'],
            ['SRCOMP' => '1', 'SRNAME' => 'History Record 2', 'SRCODE' => 'H002', 'SRDLTC' => 'A'],
            ['SRCOMP' => '1', 'SRNAME' => 'History Deleted',  'SRCODE' => 'H003', 'SRDLTC' => 'D'],
            ['SRCOMP' => '2', 'SRNAME' => 'History Company 2', 'SRCODE' => 'H004', 'SRDLTC' => 'A'],
        ]);
    }

    // ==================== IDENTITY MAP REWRITE ====================

    public function test_maps_are_rewritten_to_identity(): void
    {
        $model = new TestUnionModel;
        $maps = $model->getMaps();

        // Maps should be identity: mapped_name => mapped_name
        $this->assertEquals('company_number', $maps['company_number'] ?? null);
        $this->assertEquals('name', $maps['name'] ?? null);
        $this->assertEquals('code', $maps['code'] ?? null);
        $this->assertEquals('delete_code', $maps['delete_code'] ?? null);

        // Should NOT have DB column names as keys
        $this->assertArrayNotHasKey('SRCOMP', $maps);
        $this->assertArrayNotHasKey('SRNAME', $maps);
    }

    public function test_reverse_maps_are_identity(): void
    {
        $model = new TestUnionModel;
        $reverseMaps = $model->getReverseMaps();

        // Reverse maps should also be identity
        $this->assertEquals('company_number', $reverseMaps['company_number'] ?? null);
        $this->assertEquals('name', $reverseMaps['name'] ?? null);
        $this->assertEquals('code', $reverseMaps['code'] ?? null);
    }

    public function test_source_column_map_preserves_originals(): void
    {
        $model = new TestUnionModel;
        $sourceMap = $model->getSourceColumnMap();

        // Source map should have DB_COL => mapped_name
        $this->assertEquals('company_number', $sourceMap['SRCOMP']);
        $this->assertEquals('name', $sourceMap['SRNAME']);
        $this->assertEquals('code', $sourceMap['SRCODE']);
        $this->assertEquals('delete_code', $sourceMap['SRDLTC']);
    }

    // ==================== BUILDER TYPE ====================

    public function test_returns_read_only_builder(): void
    {
        $builder = TestUnionModel::query();

        $this->assertInstanceOf(ReadOnlyMappedBuilder::class, $builder);
    }

    // ==================== QUERY FUNCTIONALITY ====================

    public function test_query_returns_data_from_both_sources(): void
    {
        $results = TestUnionModel::all();

        // Default filters: active (SRDLTC='A') + company 1 (SRCOMP='1')
        // Source 1: L001, L002 (2 active company 1)
        // Source 2: H001, H002 (2 active company 1)
        $this->assertCount(4, $results);

        $codes = $results->pluck('code')->sort()->values()->all();
        $this->assertEquals(['H001', 'H002', 'L001', 'L002'], $codes);
    }

    public function test_to_array_returns_mapped_column_names(): void
    {
        $record = TestUnionModel::first();
        $this->assertNotNull($record);

        $array = $record->toArray();

        $this->assertArrayHasKey('company_number', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('delete_code', $array);
        $this->assertArrayHasKey('_source', $array);

        // Should NOT have raw DB column names
        $this->assertArrayNotHasKey('SRCOMP', $array);
        $this->assertArrayNotHasKey('SRNAME', $array);
    }

    public function test_source_column_identifies_origin_table(): void
    {
        $results = TestUnionModel::all();

        $sources = $results->pluck('_source')->unique()->sort()->values()->all();
        $this->assertEquals(['test_source_1', 'test_source_2'], $sources);

        // Records from source 1 should have source 1's table
        $liveRecord = $results->firstWhere('code', 'L001');
        $this->assertEquals('test_source_1', $liveRecord->_source);

        // Records from source 2 should have source 2's table
        $historyRecord = $results->firstWhere('code', 'H001');
        $this->assertEquals('test_source_2', $historyRecord->_source);
    }

    public function test_property_access_via_mapped_names(): void
    {
        $record = TestUnionModel::first();
        $this->assertNotNull($record);

        $this->assertNotNull($record->code);
        $this->assertNotNull($record->name);
        $this->assertEquals(1, $record->company_number);
    }

    public function test_where_with_mapped_column_names(): void
    {
        $results = TestUnionModel::where('code', 'L001')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Live Record 1', $results->first()->name);
    }

    public function test_exists_works(): void
    {
        $this->assertTrue(TestUnionModel::where('code', 'L001')->exists());
        $this->assertFalse(TestUnionModel::where('code', 'NONEXISTENT')->exists());
    }

    public function test_first_returns_correct_model_type(): void
    {
        $record = TestUnionModel::first();

        $this->assertInstanceOf(TestUnionModel::class, $record);
    }

    public function test_limit_works(): void
    {
        $results = TestUnionModel::limit(2)->get();

        $this->assertCount(2, $results);
    }

    // ==================== UNFILTERED SCOPE ====================

    public function test_unfiltered_returns_all_records(): void
    {
        $results = TestUnionModel::unfiltered()->get();

        // All 8 records from both sources (no filters)
        $this->assertCount(8, $results);
    }

    public function test_with_inactive_includes_deleted(): void
    {
        $results = TestUnionModel::withInactive()->get();

        // Company 1 records including deleted: L001, L002, L003, H001, H002, H003
        $this->assertCount(6, $results);
    }

    public function test_with_all_companies_includes_all(): void
    {
        $results = TestUnionModel::withAllCompanies()->get();

        // Active records from all companies: L001, L002, L004, H001, H002, H004
        $this->assertCount(6, $results);
    }

    // ==================== WRITE PREVENTION ====================

    public function test_save_throws_read_only_exception(): void
    {
        $model = new TestUnionModel;
        $model->forceFill(['code' => 'TEST']);

        $this->expectException(ReadOnlyModelException::class);
        $model->save();
    }

    public function test_delete_throws_read_only_exception(): void
    {
        $record = TestUnionModel::first();
        $this->assertNotNull($record);

        $this->expectException(ReadOnlyModelException::class);
        $record->delete();
    }

    public function test_builder_insert_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        TestUnionModel::query()->insert(['code' => 'TEST']);
    }

    public function test_builder_update_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        TestUnionModel::query()->update(['name' => 'changed']);
    }

    public function test_builder_delete_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        TestUnionModel::where('code', 'L001')->delete();
    }

    public function test_builder_upsert_throws_read_only_exception(): void
    {
        $this->expectException(ReadOnlyModelException::class);
        TestUnionModel::query()->upsert([['code' => 'TEST']], ['code']);
    }
}
