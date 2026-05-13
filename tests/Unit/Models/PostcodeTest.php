<?php

namespace Tests\Unit\Models;

use App\Models\Postcode;
use App\Models\PropertySale;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostcodeTest extends TestCase
{
    #[Test]
    public function primary_key_is_postcode(): void
    {
        $this->assertEquals('postcode', (new Postcode())->getKeyName());
    }

    #[Test]
    public function not_incrementing(): void
    {
        $this->assertFalse((new Postcode())->getIncrementing());
    }

    #[Test]
    public function key_type_is_string(): void
    {
        $this->assertEquals('string', (new Postcode())->getKeyType());
    }

    #[Test]
    public function no_timestamps(): void
    {
        $this->assertFalse((new Postcode())->usesTimestamps());
    }

    #[Test]
    public function lat_lng_cast_to_float(): void
    {
        $model = new Postcode(['latitude' => '51.5074', 'longitude' => '-0.1278']);
        $this->assertIsFloat($model->latitude);
        $this->assertIsFloat($model->longitude);
    }

    #[Test]
    public function has_many_sales(): void
    {
        $relation = (new Postcode())->sales();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertInstanceOf(PropertySale::class, $relation->getRelated());
    }

    #[Test]
    public function near_scope_uses_st_dwithin(): void
    {
        $sql = (new Postcode())->newQuery()->near(51.5, -0.1, 1.0)->toSql();
        $this->assertStringContainsString('ST_DWithin', $sql);
    }

    #[Test]
    public function near_scope_converts_miles_to_metres(): void
    {
        $bindings = (new Postcode())->newQuery()->near(51.5, -0.1, 2.0)->getBindings();
        $this->assertContains(2 * 1609.34, $bindings);
    }
}
