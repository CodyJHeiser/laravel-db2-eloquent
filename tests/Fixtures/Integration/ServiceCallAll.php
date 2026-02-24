<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Concerns\HasUnionSources;
use CodyJHeiser\Db2Eloquent\Model;

/**
 * Integration fixture: ServiceCallAll (read-only)
 * Base class that defines shared maps, casts, and relationships.
 * Combines ServiceCallLive and ServiceCallHistory via UNION ALL.
 *
 * ServiceCallLive and ServiceCallHistory extend this class and
 * override $table to query their individual tables normally.
 *
 * IMPORTANT: READ-ONLY by design. All write operations throw ReadOnlyModelException.
 */
class ServiceCallAll extends Model
{
    use HasUnionSources;

    protected $connection = 'vai';
    protected string $schema = 'R60FILES';
    protected $table = 'SBSCHD'; // Fallback for connection/schema resolution

    protected array $unionSources = [
        ServiceCallLive::class,
        ServiceCallHistory::class,
    ];

    protected $casts = [
        'SHCLDT' => IbmDate::class . ':date',
        'SHSCDT' => IbmDate::class . ':date',
    ];

    /**
     * Shared column mappings for service call tables.
     * Only columns common to both SBSCHD and SBHSHD.
     */
    protected array $maps = [
        'SHCMP'  => 'company_number',
        'SHCUST' => 'customer_number',
        'SHCLNO' => 'call_number',
        'SHCLST' => 'status',
        'SHCLDT' => 'call_date',
        'SHTEC#' => 'technician_number',
        'SHTEC2' => 'technician_number_2',
        'SHSCDT' => 'scheduled_date',
        'SHORD'  => 'order_number',
        'SHPRI'  => 'priority_code',
        'SHCCTT' => 'created_at',
        'SHOTOT' => 'call_value',
        'SHETA'  => 'estimated_arrival_time',
        'SHADD1' => 'address_1',
        'SHADD2' => 'address_2',
        'SHCITY' => 'city',
        'SHSTAT' => 'state',
        'SHZIP'  => 'zip_code',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, ['customer_number']);
    }
}
