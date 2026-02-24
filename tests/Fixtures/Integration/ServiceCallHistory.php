<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Casts\IbmDate;

/**
 * Integration fixture: ServiceCallHistory (read-only)
 * Mirrors a live model from a consuming codebase.
 *
 * Table: R60FILES.SBHSHD
 *
 * Extends ServiceCallAll to reuse shared maps, casts, and relationships.
 * Overrides $table and $casts where the history table differs.
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class ServiceCallHistory extends ServiceCallAll
{
    protected $table = 'SBHSHD';

    protected $casts = [
        'SHCLDT' => IbmDate::class,
        'SHSCDT' => IbmDate::class,
    ];
}
