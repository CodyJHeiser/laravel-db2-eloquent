<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Model;

/**
 * Integration fixture: Customer Master File (read-only)
 * Mirrors a live model from a consuming codebase.
 *
 * Table: R60FILES.VARCUST
 * Extension: R60FSDTA.VARCUSTXT
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class Customer extends Model
{
    protected $connection = 'vai';
    protected string $schema = 'R60FILES';
    protected $table = 'VARCUST';

    protected array $extensions = [
        'R60FSDTA.VARCUSTXT' => [
            'join' => [
                'CXCMP'  => 'RMCMP',
                'CXCUST' => 'RMCUST',
            ],
            'maps' => [
                'CXDEL'  => 'delete_code',
                'CXCMP'  => 'company_number',
                'CXCUST' => 'customer_number',
                'CXCTYP' => 'customer_type',
                'CXCLBA' => 'lb_affiliation',
            ],
        ],
    ];

    protected array $maps = [
        'RMDEL'  => 'delete_code',
        'RMCMP'  => 'company_number',
        'RMCUST' => 'customer_number',
        'RMNAME' => 'name',
        'RMADD1' => 'address_1',
        'RMADD2' => 'address_2',
        'RMADD3' => 'address_3',
        'RMCITY' => 'city',
        'RMSTAT' => 'state',
        'RMMZIP' => 'zip',
        'RMMFON' => 'phone',
        'RMMFAX' => 'phone_alt',
        'RMEMAL' => 'email',
    ];

    public function latitude()
    {
        return $this->hasOne(UserDefinedField::class, [
            'company_number' => 'company_number',
            'key_value' => 'customer_number',
        ])->where('field_number', 11);
    }
}
