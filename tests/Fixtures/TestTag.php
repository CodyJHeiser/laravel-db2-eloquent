<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Test fixture model for belongsToMany relationship tests.
 */
class TestTag extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_tags';
    protected string $schema = '';

    protected $guarded = [];

    protected array $maps = [
        'TGCODE' => 'tag_code',
        'TGNAME' => 'tag_name',
        'TGCOMP' => 'company_number',
        'TGDLTC' => 'delete_code',
    ];

    /**
     * Override to skip schema prefix for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * A tag belongs to many items (multi-column pivot).
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            TestItemRelation::class,
            'test_item_tag',
            ['PTTAG', 'PTTAGCOMP'],
            ['PTITEM', 'PTITCOMP'],
            ['TGCODE', 'TGCOMP'],
            ['ITCODE', 'ITCOMP']
        );
    }
}
