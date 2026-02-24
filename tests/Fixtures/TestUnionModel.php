<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Concerns\HasUnionSources;
use CodyJHeiser\Db2Eloquent\Model;

/**
 * Test fixture: union model combining two source tables.
 * Base class that defines shared maps. Children extend and override $table.
 */
class TestUnionModel extends Model
{
    use HasUnionSources;

    protected $connection = 'testing';
    protected $table = 'test_source_1'; // Fallback
    protected string $schema = '';

    protected $guarded = [];

    protected array $unionSources = [
        TestUnionSource1::class,
        TestUnionSource2::class,
    ];

    protected array $maps = [
        'SRCOMP' => 'company_number',
        'SRNAME' => 'name',
        'SRCODE' => 'code',
        'SRDLTC' => 'delete_code',
    ];

    /**
     * Override to skip schema prefix for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
