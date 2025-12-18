<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Test fixture model for testing the HasDatabaseSchema trait.
 * Uses a real schema prefix unlike the other test fixtures.
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
class TestSchemaModel extends Model
{
    protected $connection = 'testing';
    protected $table = 'VINITEM';
    protected string $schema = 'R60FILES';
    protected string $schemaPrefixProd = 'R';
    protected string $schemaPrefixTest = 'T';

    protected $guarded = [];

    protected array $maps = [
        'ICITEM' => 'item_number',
        'ICDESC' => 'description',
    ];
}
