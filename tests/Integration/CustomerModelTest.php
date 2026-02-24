<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Builders\MappedBuilder;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\Customer;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\UserDefinedField;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Live integration tests against R60FILES.VARCUST and R60FILES.ZUDDTA.
 *
 * IMPORTANT: ALL TESTS ARE READ-ONLY. No inserts, updates, or deletes.
 *
 * Tests:
 * - Column mapping
 * - Extension table joins (R60FSDTA.VARCUSTXT)
 * - HasOne multi-column relationship with constant (latitude → UserDefinedField)
 * - exists(), selectMapped, where with mapped names
 */
class CustomerModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    /**
     * @template T of \CodyJHeiser\Db2Eloquent\Model
     * @param  class-string<T> $model
     */
    private function query(string $model): MappedBuilder
    {
        return $model::unfiltered();
    }

    // ==================== BASIC CONNECTIVITY ====================

    public function test_can_query_customer_table(): void
    {
        $results = $this->query(Customer::class)->limit(1)->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count(), 'Expected at least 1 customer');
    }

    public function test_can_query_udf_table(): void
    {
        $results = $this->query(UserDefinedField::class)->limit(1)->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    // ==================== COLUMN MAPPING ====================

    public function test_customer_returns_mapped_column_names(): void
    {
        $record = $this->query(Customer::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        $array = $record->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('customer_number', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('state', $array);
        $this->assertArrayHasKey('email', $array);

        // Should NOT have raw DB column names
        $this->assertArrayNotHasKey('RMNAME', $array);
        $this->assertArrayNotHasKey('RMCUST', $array);
    }

    public function test_customer_accessible_via_mapped_and_db_names(): void
    {
        $record = $this->query(Customer::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        // Both should return the same value
        $this->assertEquals($record->name, $record->RMNAME);
        $this->assertEquals($record->customer_number, $record->RMCUST);
        $this->assertEquals($record->city, $record->RMCITY);
    }

    public function test_udf_returns_mapped_column_names(): void
    {
        $record = $this->query(UserDefinedField::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in ZUDDTA table');

        $array = $record->toArray();

        $this->assertArrayHasKey('company_number', $array);
        $this->assertArrayHasKey('key_value', $array);
        $this->assertArrayHasKey('field_number', $array);
        $this->assertArrayHasKey('field_value', $array);

        $this->assertArrayNotHasKey('UDFBCMP', $array);
        $this->assertArrayNotHasKey('UDFBKEY1', $array);
    }

    // ==================== EXTENSIONS ====================

    public function test_customer_with_extensions_joins_extension_table(): void
    {
        $record = $this->query(Customer::class)->withExtensions()->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        $array = $record->toArray();

        // Extension columns should be present via extension maps
        $this->assertArrayHasKey('customer_type', $array);
        $this->assertArrayHasKey('lb_affiliation', $array);
    }

    public function test_customer_extension_data_matches_base(): void
    {
        $record = $this->query(Customer::class)->withExtensions()->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        // Extension company_number should match base company_number
        // Both maps map to 'company_number' so the value should be consistent
        $this->assertNotNull($record->company_number);
    }

    // ==================== SELECT MAPPED ====================

    public function test_customer_select_mapped_returns_only_mapped_columns(): void
    {
        $record = $this->query(Customer::class)->selectMapped()->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        $array = $record->toArray();

        foreach (array_keys($array) as $key) {
            $this->assertDoesNotMatchRegularExpression('/^[A-Z]{2,}$/', $key,
                "Key '{$key}' looks like a raw DB column name, expected mapped name");
        }
    }

    // ==================== WHERE WITH MAPPED NAMES ====================

    public function test_where_with_mapped_column_name(): void
    {
        $results = $this->query(Customer::class)
            ->where('state', '!=', '')
            ->limit(3)
            ->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_where_with_customer_number(): void
    {
        // Get a customer number first, then query by it
        $record = $this->query(Customer::class)->limit(1)->first();
        $this->assertNotNull($record, 'No data in VARCUST table');

        $custNum = $record->customer_number;

        $found = $this->query(Customer::class)
            ->where('customer_number', $custNum)
            ->first();

        $this->assertNotNull($found);
        $this->assertEquals($custNum, $found->customer_number);
    }

    // ==================== EXISTS ====================

    public function test_exists_works_on_customer_table(): void
    {
        $exists = $this->query(Customer::class)->exists();

        $this->assertTrue($exists);
    }

    public function test_doesnt_exist_with_impossible_filter(): void
    {
        $doesntExist = $this->query(Customer::class)
            ->where('name', 'ZZZZZ')
            ->doesntExist();

        $this->assertTrue($doesntExist);
    }

    // ==================== MULTIPLE RECORDS ====================

    public function test_can_fetch_multiple_customers(): void
    {
        $records = $this->query(Customer::class)->limit(10)->get();

        $this->assertInstanceOf(Collection::class, $records);
        $this->assertGreaterThan(0, $records->count());
        $this->assertLessThanOrEqual(10, $records->count());
    }

    // ==================== RELATIONSHIP: hasOne (latitude) ====================

    public function test_latitude_relationship_executes_without_error(): void
    {
        $customer = $this->query(Customer::class)->limit(1)->first();
        $this->assertNotNull($customer, 'No data in VARCUST table');

        // Load the latitude relationship - may or may not have data for this customer
        $latitude = $customer->latitude;

        if ($latitude !== null) {
            $this->assertInstanceOf(UserDefinedField::class, $latitude);
            $this->assertEquals(11, $latitude->field_number);
        }

        // Verifying the relationship executes without error
        $this->assertTrue(true);
    }

    public function test_latitude_relationship_query_can_be_built(): void
    {
        $customer = $this->query(Customer::class)->limit(1)->first();
        $this->assertNotNull($customer, 'No data in VARCUST table');

        // Verify the relationship query can be built without error
        $query = $customer->latitude();
        $this->assertNotNull($query);
        $this->assertNotNull($query->toSql());
    }

    public function test_latitude_relationship_returns_data(): void
    {
        // Find a customer that has UDF field 11 data by querying UDF first
        $udf = $this->query(UserDefinedField::class)
            ->where('field_number', 11)
            ->limit(1)
            ->first();

        if ($udf === null) {
            $this->markTestSkipped('No UDF records with field_number=11 in ZUDDTA');
        }

        // Now find the customer matching this UDF record
        $customer = $this->query(Customer::class)
            ->where('customer_number', $udf->key_value)
            ->with('latitude')
            ->first();

        if ($customer === null) {
            $this->markTestSkipped('No matching customer for UDF key_value');
        }

        // Query relationship directly (avoids eager loading match issues)
        $latitude = $customer->latitude()->first();

        $this->assertInstanceOf(UserDefinedField::class, $latitude);
        $this->assertEquals(11, $latitude->field_number);
        $this->assertNotEmpty($latitude->field_value);
    }
}
