<?php

namespace CodyJHeiser\Db2Eloquent\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rufhausen\DB2Driver\DB2ServiceProvider;

abstract class TestCase extends Orchestra
{
    protected bool $useDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->useDatabase) {
            if ($this->useDb2Connection()) {
                // Real DB2 - no setup needed, use existing tables
            } elseif ($this->canUseSqlite()) {
                $this->setUpSqliteDatabase();
            }
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            DB2ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Default to SQLite for unit tests and CI
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Check for real DB2 connection via environment variables
        if ($this->useDb2Connection()) {
            $app['config']->set('database.connections.vai', [
                'driver' => 'db2',
                'host' => env('IBM_DB_HOST'),
                'port' => env('IBM_DB_PORT', 50000),
                'database' => env('IBM_DB_DATABASE'),
                'username' => env('IBM_DB_USERNAME'),
                'password' => env('IBM_DB_PASSWORD'),
                'schema' => env('IBM_DB_SCHEMA', 'QGPL'),
                'odbc_keywords' => [
                    'NAM' => 1,
                    'CMT' => 0,
                    'DFT' => 5,
                ],
            ]);
        } else {
            // Fallback to SQLite for mocking
            $app['config']->set('database.connections.vai', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }

    protected function useDb2Connection(): bool
    {
        return !empty(env('IBM_DB_HOST')) && extension_loaded('pdo_odbc');
    }

    protected function canUseSqlite(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    protected function setUpSqliteDatabase(): void
    {
        if (!$this->canUseSqlite()) {
            return;
        }

        // Create test tables for SQLite
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_items', function ($table) {
            $table->string('ICITEM', 20)->primary();
            $table->string('ICDESC', 50)->nullable();
            $table->string('ICCOMP', 2)->default('1');
            $table->string('ICDLTC', 1)->default('A');
            $table->integer('ICCOST')->default(0);
            $table->integer('ICDATE')->default(0);
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_warehouses', function ($table) {
            $table->string('WHCOMP', 2);
            $table->string('WHCODE', 10);
            $table->string('WHDESC', 50)->nullable();
            $table->string('WHDLTC', 1)->default('A');
            $table->primary(['WHCOMP', 'WHCODE']);
        });

        // Extension tables for HasExtensions tests
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_item_extensions', function ($table) {
            $table->string('EXITEM', 20);
            $table->string('EXCOMP', 2);
            $table->string('EXDATA', 100)->nullable();
            $table->string('EXNOTE', 200)->nullable();
            $table->primary(['EXITEM', 'EXCOMP']);
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_item_details', function ($table) {
            $table->id();
            $table->string('DTITEM', 20);
            $table->string('DTINFO', 100)->nullable();
        });

        // Department tables for hasOneThrough/hasManyThrough tests
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_departments', function ($table) {
            $table->string('DPCODE', 10);
            $table->string('DPNAME', 50)->nullable();
            $table->string('DPCOMP', 2)->default('1');
            $table->string('DPDLTC', 1)->default('A');
            $table->primary(['DPCODE', 'DPCOMP']);
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('t60_test_departments', function ($table) {
            $table->string('DPCODE', 10);
            $table->string('DPNAME', 50)->nullable();
            $table->string('DPCOMP', 2)->default('1');
            $table->string('DPDLTC', 1)->default('A');
            $table->primary(['DPCODE', 'DPCOMP']);
        });

        // Relationship test tables (simulate production schema)
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_categories', function ($table) {
            $table->string('CTCODE', 10);
            $table->string('CTNAME', 50)->nullable();
            $table->string('CTCOMP', 2)->default('1');
            $table->string('CTDEPT', 10)->nullable();
            $table->string('CTDLTC', 1)->default('A');
            $table->primary(['CTCODE', 'CTCOMP']);
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('test_items_rel', function ($table) {
            $table->string('ITCODE', 20)->primary();
            $table->string('ITNAME', 50)->nullable();
            $table->string('ITCAT', 10)->nullable();
            $table->string('ITCOMP', 2)->default('1');
            $table->string('ITDLTC', 1)->default('A');
        });

        // Simulate test schema tables (T60FILES prefix in real DB2)
        // In SQLite we use prefixed table names to simulate separate schemas
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('t60_test_categories', function ($table) {
            $table->string('CTCODE', 10);
            $table->string('CTNAME', 50)->nullable();
            $table->string('CTCOMP', 2)->default('1');
            $table->string('CTDEPT', 10)->nullable();
            $table->string('CTDLTC', 1)->default('A');
            $table->primary(['CTCODE', 'CTCOMP']);
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('t60_test_items_rel', function ($table) {
            $table->string('ITCODE', 20)->primary();
            $table->string('ITNAME', 50)->nullable();
            $table->string('ITCAT', 10)->nullable();
            $table->string('ITCOMP', 2)->default('1');
            $table->string('ITDLTC', 1)->default('A');
        });
    }

    protected function skipIfNoDatabaseDriver(): void
    {
        if (!$this->canUseSqlite() && !$this->useDb2Connection()) {
            $this->markTestSkipped('No database driver available (SQLite or DB2)');
        }
    }

    protected function skipIfNoDb2(): void
    {
        if (!$this->useDb2Connection()) {
            $this->markTestSkipped('DB2 connection not configured. Set IBM_DB_HOST, IBM_DB_DATABASE, IBM_DB_USERNAME, IBM_DB_PASSWORD env vars.');
        }
    }
}
