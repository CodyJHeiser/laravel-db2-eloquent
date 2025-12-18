<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Casts;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Casts\IbmDateNullable;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class IbmDateNullableTest extends TestCase
{
    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {};
    }

    public function test_get_returns_null_for_no_date_value(): void
    {
        $cast = new IbmDateNullable();

        $result = $cast->get($this->model, 'date', 99999999, []);

        $this->assertNull($result);
    }

    public function test_get_returns_carbon_for_valid_date(): void
    {
        $cast = new IbmDateNullable();

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(12, $result->month);
        $this->assertEquals(17, $result->day);
    }

    public function test_get_returns_null_for_invalid_month(): void
    {
        $cast = new IbmDateNullable();

        $this->assertNull($cast->get($this->model, 'date', 20251317, [])); // month 13
        $this->assertNull($cast->get($this->model, 'date', 20250017, [])); // month 00
    }

    public function test_get_returns_null_for_invalid_day(): void
    {
        $cast = new IbmDateNullable();

        $this->assertNull($cast->get($this->model, 'date', 20251232, [])); // day 32
        $this->assertNull($cast->get($this->model, 'date', 20251200, [])); // day 00
    }

    public function test_get_returns_null_for_invalid_date(): void
    {
        $cast = new IbmDateNullable();

        // February 30th doesn't exist
        $this->assertNull($cast->get($this->model, 'date', 20250230, []));
        // February 29th on non-leap year
        $this->assertNull($cast->get($this->model, 'date', 20250229, []));
    }

    public function test_get_handles_leap_year(): void
    {
        $cast = new IbmDateNullable();

        // February 29th on leap year (2024)
        $result = $cast->get($this->model, 'date', 20240229, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(29, $result->day);
    }

    public function test_set_null_returns_no_date_value(): void
    {
        $cast = new IbmDateNullable();

        $result = $cast->set($this->model, 'date', null, []);

        $this->assertEquals(99999999, $result);
    }

    public function test_set_from_carbon_returns_integer(): void
    {
        $cast = new IbmDateNullable();
        $date = Carbon::create(2025, 12, 17);

        $result = $cast->set($this->model, 'date', $date, []);

        $this->assertEquals(20251217, $result);
    }

    public function test_no_date_value_constant_is_correct(): void
    {
        $this->assertEquals(99999999, IbmDateNullable::NO_DATE_VALUE);
    }

    public function test_get_with_format_returns_null_for_no_date(): void
    {
        $cast = new IbmDateNullable('Y-m-d');

        $result = $cast->get($this->model, 'date', 99999999, []);

        $this->assertNull($result);
    }

    public function test_get_with_format_returns_string_for_valid_date(): void
    {
        $cast = new IbmDateNullable('Y-m-d');

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertEquals('2025-12-17', $result);
    }
}
