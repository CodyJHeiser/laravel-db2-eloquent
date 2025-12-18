<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Casts;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class IbmDateTest extends TestCase
{
    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {};
    }

    public function test_get_returns_carbon_instance_by_default(): void
    {
        $cast = new IbmDate();

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(12, $result->month);
        $this->assertEquals(17, $result->day);
    }

    public function test_get_returns_null_for_null_value(): void
    {
        $cast = new IbmDate();

        $this->assertNull($cast->get($this->model, 'date', null, []));
    }

    public function test_get_returns_null_for_zero(): void
    {
        $cast = new IbmDate();

        $this->assertNull($cast->get($this->model, 'date', 0, []));
    }

    public function test_get_returns_null_for_empty_string(): void
    {
        $cast = new IbmDate();

        $this->assertNull($cast->get($this->model, 'date', '', []));
    }

    public function test_get_returns_null_for_invalid_length(): void
    {
        $cast = new IbmDate();

        $this->assertNull($cast->get($this->model, 'date', 2025121, [])); // 7 digits
        $this->assertNull($cast->get($this->model, 'date', 202512170, [])); // 9 digits
    }

    public function test_get_with_format_returns_string(): void
    {
        $cast = new IbmDate('Y-m-d');

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertIsString($result);
        $this->assertEquals('2025-12-17', $result);
    }

    public function test_get_with_date_shortcut(): void
    {
        $cast = new IbmDate('date');

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertEquals('2025-12-17', $result);
    }

    public function test_get_with_us_shortcut(): void
    {
        $cast = new IbmDate('us');

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertEquals('12/17/2025', $result);
    }

    public function test_get_with_eu_shortcut(): void
    {
        $cast = new IbmDate('eu');

        $result = $cast->get($this->model, 'date', 20251217, []);

        $this->assertEquals('17/12/2025', $result);
    }

    public function test_set_from_carbon_instance(): void
    {
        $cast = new IbmDate();
        $date = Carbon::create(2025, 12, 17);

        $result = $cast->set($this->model, 'date', $date, []);

        $this->assertEquals(20251217, $result);
    }

    public function test_set_from_integer(): void
    {
        $cast = new IbmDate();

        $result = $cast->set($this->model, 'date', 20251217, []);

        $this->assertEquals(20251217, $result);
    }

    public function test_set_from_string(): void
    {
        $cast = new IbmDate();

        $result = $cast->set($this->model, 'date', '2025-12-17', []);

        $this->assertEquals(20251217, $result);
    }

    public function test_set_from_null_returns_null(): void
    {
        $cast = new IbmDate();

        $result = $cast->set($this->model, 'date', null, []);

        $this->assertNull($result);
    }
}
