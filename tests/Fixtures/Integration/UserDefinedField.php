<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Model;

/**
 * Integration fixture: User Defined Field Data (read-only)
 * Mirrors a live model from a consuming codebase.
 *
 * Table: R60FILES.ZUDDTA
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class UserDefinedField extends Model
{
    protected $connection = 'vai';
    protected string $schema = 'R60FILES';
    protected $table = 'ZUDDTA';

    protected array $maps = [
        'UDFBCMP'   => 'company_number',
        'UDFBKEY1'  => 'key_value',
        'UDFBFILD'  => 'field_number',
        'UDFBVALUE' => 'field_value',
    ];
}
