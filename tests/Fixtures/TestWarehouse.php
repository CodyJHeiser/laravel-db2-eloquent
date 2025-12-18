<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Test fixture model for unit tests.
 *
 * Scopes from HasAutoFiltering:
 * @method static Builder withInactive()
 * @method static Builder withAllCompanies()
 * @method static Builder forCompany(string $company)
 * @method static Builder unfiltered()
 *
 * Scopes from HasDatabaseSchema:
 * @method static Builder testing()
 *
 * Scopes from HasColumnMapping:
 * @method static Builder selectAll()
 * @method static Builder selectMapped()
 *
 * Scopes from HasQueryLogging:
 * @method static Builder logQuery(string|array|null $channels = 'stderr')
 *
 * Scopes from HasExtensions:
 * @method static Builder withExtensions(?array $only = null)
 * @method static Builder withExtension(string $extTable)
 * @method static Builder whereHasExtension(string $extTable, string $operator = '>=', int $count = 1)
 * @method static Builder whereDoesntHaveExtension(string $extTable)
 * @method static Builder withWhereHasExtension(string $extTable, string $operator = '>=', int $count = 1)
 *
 * @method static Collection all($columns = ['*'])
 */
class TestWarehouse extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_warehouses';
    protected string $schema = '';

    protected $guarded = [];

    protected array $maps = [
        'WHCOMP' => 'company_number',
        'WHCODE' => 'warehouse_code',
        'WHDESC' => 'description',
        'WHDLTC' => 'delete_code',
    ];

    /**
     * Override to skip schema prefix for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
