<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Model;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestSchemaModel;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

/**
 * Tests for the HasDatabaseSchema trait.
 * Verifies schema prefix handling and testing() scope functionality.
 */
class HasDatabaseSchemaTest extends TestCase
{
    // ==================== SCHEMA PREFIX TESTS ====================

    public function test_get_table_includes_schema_prefix(): void
    {
        $model = new TestSchemaModel();

        $this->assertEquals('R60FILES.VINITEM', $model->getTable());
    }

    public function test_get_schema_returns_schema(): void
    {
        $model = new TestSchemaModel();

        $this->assertEquals('R60FILES', $model->getSchema());
    }

    public function test_default_schema_is_r60files(): void
    {
        $model = new TestSchemaModel();

        $this->assertEquals('R60FILES', $model->getSchema());
    }

    // ==================== TESTING SCOPE TESTS ====================

    public function test_testing_scope_sets_use_test_schema_flag(): void
    {
        $query = TestSchemaModel::testing();
        $model = $query->getModel();

        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($model));
    }

    public function test_testing_scope_changes_schema_prefix_r_to_t(): void
    {
        $query = TestSchemaModel::testing();

        // Get the 'from' clause which should have the test schema
        $from = $query->getQuery()->from;

        $this->assertEquals('T60FILES.VINITEM', $from);
    }

    public function test_testing_scope_with_r60fsdta_schema(): void
    {
        // Create a model with R60FSDTA schema
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'VARCUSTXT';
            protected string $schema = 'R60FSDTA';
            protected $guarded = [];
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        $this->assertEquals('T60FSDTA.VARCUSTXT', $from);
    }

    public function test_effective_schema_returns_production_by_default(): void
    {
        $model = new TestSchemaModel();

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getEffectiveSchema');
        $method->setAccessible(true);

        $this->assertEquals('R60FILES', $method->invoke($model));
    }

    public function test_effective_schema_returns_test_schema_when_flag_set(): void
    {
        $model = new TestSchemaModel();

        $reflection = new \ReflectionClass($model);

        // Set the protected property
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);
        $property->setValue($model, true);

        // Call the protected method
        $method = $reflection->getMethod('getEffectiveSchema');
        $method->setAccessible(true);

        $this->assertEquals('T60FILES', $method->invoke($model));
    }

    // ==================== SCHEMA PROPAGATION TESTS ====================

    public function test_new_instance_propagates_test_schema_flag(): void
    {
        $model = new TestSchemaModel();

        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);
        $property->setValue($model, true);

        $newInstance = $model->newInstance();

        $newReflection = new \ReflectionClass($newInstance);
        $newProperty = $newReflection->getProperty('useTestSchema');
        $newProperty->setAccessible(true);

        $this->assertTrue($newProperty->getValue($newInstance));
    }

    public function test_new_instance_does_not_propagate_when_flag_false(): void
    {
        $model = new TestSchemaModel();

        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('useTestSchema');
        $property->setAccessible(true);
        $property->setValue($model, false);

        $newInstance = $model->newInstance();

        $newReflection = new \ReflectionClass($newInstance);
        $newProperty = $newReflection->getProperty('useTestSchema');
        $newProperty->setAccessible(true);

        $this->assertFalse($newProperty->getValue($newInstance));
    }

    // ==================== TABLE NAME TESTS ====================

    public function test_table_with_existing_dot_is_not_modified(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'CUSTOM.MYTABLE';  // Already has schema
            protected string $schema = 'R60FILES';
            protected $guarded = [];
        };

        // Should return as-is since it already has a dot
        $this->assertEquals('CUSTOM.MYTABLE', $model->getTable());
    }

    // ==================== QUERY BUILDING TESTS ====================

    public function test_testing_scope_generates_correct_sql(): void
    {
        $query = TestSchemaModel::testing()->where('item_number', 'TEST');

        $sql = $query->toSql();

        // Should reference the test schema table
        $this->assertStringContainsString('T60FILES', $sql);
        $this->assertStringContainsString('VINITEM', $sql);
    }

    public function test_production_query_generates_correct_sql(): void
    {
        $query = TestSchemaModel::where('item_number', 'TEST');

        $sql = $query->toSql();

        // Should reference the production schema table
        $this->assertStringContainsString('R60FILES', $sql);
        $this->assertStringContainsString('VINITEM', $sql);
    }

    public function test_can_chain_testing_scope_with_other_scopes(): void
    {
        // This tests that testing() can be chained with other query methods
        $query = TestSchemaModel::testing()
            ->where('item_number', 'TEST')
            ->orderBy('description');

        $sql = $query->toSql();

        $this->assertStringContainsString('T60FILES', $sql);
        $this->assertStringContainsString('order by', strtolower($sql));
    }

    // ==================== EXPLICIT TEST SCHEMA TESTS ====================

    public function test_explicit_test_schema_is_used_when_set(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'MYTABLE';
            protected string $schema = 'PROD_SCHEMA';
            protected ?string $testSchema = 'TEST_SCHEMA';
            protected $guarded = [];
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        $this->assertEquals('TEST_SCHEMA.MYTABLE', $from);
    }

    public function test_prefix_replacement_when_no_explicit_test_schema(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'MYTABLE';
            protected string $schema = 'PROD_DATA';
            protected string $schemaPrefixProd = 'PROD';
            protected string $schemaPrefixTest = 'TEST';
            protected $guarded = [];
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        $this->assertEquals('TEST_DATA.MYTABLE', $from);
    }

    public function test_custom_prefix_replacement(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'USERS';
            protected string $schema = 'LIVE_DB';
            protected string $schemaPrefixProd = 'LIVE';
            protected string $schemaPrefixTest = 'DEV';
            protected $guarded = [];
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        $this->assertEquals('DEV_DB.USERS', $from);
    }

    public function test_schema_unchanged_when_prefix_does_not_match(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'MYTABLE';
            protected string $schema = 'MYSCHEMA';
            protected string $schemaPrefixProd = 'PROD';  // Doesn't match MYSCHEMA
            protected string $schemaPrefixTest = 'TEST';
            protected $guarded = [];
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        // Schema unchanged because prefix doesn't match
        $this->assertEquals('MYSCHEMA.MYTABLE', $from);
    }

    public function test_get_test_schema_name_can_be_overridden(): void
    {
        $model = new class extends Model {
            protected $connection = 'testing';
            protected $table = 'MYTABLE';
            protected string $schema = 'PROD';
            protected $guarded = [];

            protected function getTestSchemaName(): string
            {
                return 'CUSTOM_TEST_SCHEMA';
            }
        };

        $query = $model->newQuery()->testing();
        $from = $query->getQuery()->from;

        $this->assertEquals('CUSTOM_TEST_SCHEMA.MYTABLE', $from);
    }
}
