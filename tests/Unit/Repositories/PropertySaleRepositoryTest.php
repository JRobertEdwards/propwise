<?php

namespace Tests\Unit\Repositories;

use App\Data\SearchFilters;
use App\Models\EpcCertificate;
use App\Models\Postcode;
use App\Models\PropertySale;
use App\Repositories\PropertySaleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PropertySaleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PropertySaleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PropertySaleRepository();
    }

    private function makeFilters(array $overrides = []): SearchFilters
    {
        return new SearchFilters(
            lat: $overrides['lat'] ?? 51.5074,
            lng: $overrides['lng'] ?? -0.1278,
            radius: $overrides['radius'] ?? 1.0,
            propertyTypes: $overrides['propertyTypes'] ?? null,
            dateFrom: $overrides['dateFrom'] ?? null,
            dateTo: $overrides['dateTo'] ?? null,
        );
    }

    #[Test]
    public function returns_paginator(): void
    {
        $result = $this->repository->search($this->makeFilters());

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function returns_sales_within_radius(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->count(2)->create();

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        $this->assertEquals(2, $result->total());
    }

    #[Test]
    public function filters_by_property_type(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['property_type' => 'T']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['property_type' => 'D']);

        $result = $this->repository->search($this->makeFilters([
            'lat' => $lat, 'lng' => $lng, 'propertyTypes' => ['T'],
        ]));

        $this->assertEquals(1, $result->total());
        $this->assertEquals('T', $result->items()[0]->property_type);
    }

    #[Test]
    public function eager_loads_epc_certificate(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        $epc = EpcCertificate::factory()->create();
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['epc_certificate_id' => $epc->id]);

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        $this->assertTrue($result->items()[0]->relationLoaded('epcCertificate'));
    }

    #[Test]
    public function results_include_distance_metres(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create();

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        $this->assertNotNull($result->items()[0]->distance_metres);
    }

    #[Test]
    public function filters_by_date_range(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['sale_date' => '2022-06-01']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['sale_date' => '2020-01-01']);

        $result = $this->repository->search($this->makeFilters([
            'lat' => $lat, 'lng' => $lng, 'dateFrom' => '2021-01-01', 'dateTo' => '2023-01-01',
        ]));

        $this->assertEquals(1, $result->total());
    }
}
