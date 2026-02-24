<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

/**
 * Integration fixture: ServiceCallLive (read-only)
 * Mirrors a live model from a consuming codebase.
 *
 * Table: R60FILES.SBSCHD
 *
 * Extends ServiceCallAll to reuse shared maps, casts, and relationships.
 * Only the table name is overridden — all union/read-only behavior is
 * automatically skipped for child classes.
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class ServiceCallLive extends ServiceCallAll
{
    protected $table = 'SBSCHD';
}
