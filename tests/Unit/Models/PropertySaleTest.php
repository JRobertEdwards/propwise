<?php

namespace Tests\Unit\Models;

use App\Models\EpcCertificate;
use App\Models\PropertySale;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PropertySaleTest extends TestCase
{
    #[Test]
    public function no_timestamps(): void
    {
        $this->assertFalse((new PropertySale())->usesTimestamps());
    }

    #[Test]
    public function price_cast_to_integer(): void
    {
        $model = new PropertySale(['price' => '265000']);
        $this->assertIsInt($model->price);
    }

    #[Test]
    public function new_build_cast_to_boolean(): void
    {
        $model = new PropertySale(['new_build' => '1']);
        $this->assertIsBool($model->new_build);
    }

    #[Test]
    public function sale_date_cast_to_date(): void
    {
        $model = new PropertySale(['sale_date' => '2023-06-15']);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $model->sale_date);
    }

    #[Test]
    public function belongs_to_epc_certificate(): void
    {
        $relation = (new PropertySale())->epcCertificate();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
        $this->assertInstanceOf(EpcCertificate::class, $relation->getRelated());
    }

    #[Test]
    public function within_radius_scope_uses_st_dwithin(): void
    {
        $sql = (new PropertySale())->newQuery()->withinRadius(51.5, -0.1, 1.0)->toSql();
        $this->assertStringContainsString('ST_DWithin', $sql);
    }

    #[Test]
    public function within_radius_scope_converts_miles_to_metres(): void
    {
        $bindings = (new PropertySale())->newQuery()->withinRadius(51.5, -0.1, 2.0)->getBindings();
        $this->assertContains(2 * 1609.34, $bindings);
    }

    #[Test]
    public function of_type_scope_filters_by_property_type(): void
    {
        $sql = (new PropertySale())->newQuery()->ofType('T')->toSql();
        $this->assertStringContainsString('property_type', $sql);
    }

    #[Test]
    public function of_type_scope_accepts_array(): void
    {
        $sql = (new PropertySale())->newQuery()->ofType(['T', 'S'])->toSql();
        $this->assertStringContainsString('property_type', $sql);
    }

    #[Test]
    public function sold_between_scope_uses_where_between(): void
    {
        $sql = (new PropertySale())->newQuery()->soldBetween('2020-01-01', '2023-12-31')->toSql();
        $this->assertStringContainsString('sale_date', $sql);
        $this->assertStringContainsString('between', strtolower($sql));
    }

    #[Test]
    public function with_epc_scope_filters_non_null(): void
    {
        $sql = (new PropertySale())->newQuery()->withEpc()->toSql();
        $this->assertStringContainsString('epc_certificate_id', $sql);
        $this->assertStringContainsString('not null', strtolower($sql));
    }

    #[Test]
    public function price_per_sqm_returns_null_without_epc(): void
    {
        $model = new PropertySale(['price' => 200000]);
        $model->setRelation('epcCertificate', null);
        $this->assertNull($model->price_per_sqm);
    }

    #[Test]
    public function price_per_sqm_calculated_correctly(): void
    {
        $epc = new EpcCertificate(['total_floor_area' => 80.0]);
        $model = new PropertySale(['price' => 200000]);
        $model->setRelation('epcCertificate', $epc);
        $this->assertEquals(2500.0, $model->price_per_sqm);
    }

    #[Test]
    public function price_per_sqm_returns_null_for_zero_area(): void
    {
        $epc = new EpcCertificate(['total_floor_area' => 0]);
        $model = new PropertySale(['price' => 200000]);
        $model->setRelation('epcCertificate', $epc);
        $this->assertNull($model->price_per_sqm);
    }
}
