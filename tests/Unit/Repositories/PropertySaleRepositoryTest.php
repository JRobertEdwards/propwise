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
            houseNumber: $overrides['houseNumber'] ?? null,
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

    #[Test]
    public function dedupes_transactions_for_same_address_and_attaches_history(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);

        $address = ['paon' => '12', 'street' => 'Oak Street', 'saon' => null];

        // Three transactions for the same physical address, different sale dates and types
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create($address + [
            'sale_date'     => '2018-03-10',
            'price'         => 310000,
            'property_type' => 'T',
        ]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create($address + [
            'sale_date'     => '2024-05-20',
            'price'         => 485000,
            'property_type' => 'S',
        ]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create($address + [
            'sale_date'     => '2011-08-01',
            'price'         => 180000,
            'property_type' => 'T',
        ]);

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        $this->assertEquals(1, $result->total(), 'Three transactions for same address should collapse to one result');

        $row = $result->items()[0];
        $this->assertEquals('2024-05-20', $row->sale_date->toDateString(), 'Displayed row should be most recent sale');
        $this->assertEquals(485000, $row->price);
        $this->assertEquals(3, (int) $row->sale_count);

        $history = $row->getRelation('saleHistory');
        $this->assertCount(2, $history, 'History excludes the displayed (latest) sale');
        $this->assertEquals('2018-03-10', $history[0]->sale_date->toDateString(), 'History ordered most-recent first');
        $this->assertEquals('2011-08-01', $history[1]->sale_date->toDateString());
    }

    #[Test]
    public function does_not_merge_distinct_addresses_in_same_postcode(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);

        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create([
            'paon' => '12', 'street' => 'Oak Street', 'saon' => null,
        ]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create([
            'paon' => '14', 'street' => 'Oak Street', 'saon' => null,
        ]);

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        $this->assertEquals(2, $result->total());
    }

    #[Test]
    public function single_sale_properties_have_empty_sale_history(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '1', 'street' => 'Oak Street']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '2', 'street' => 'Oak Street']);

        $result = $this->repository->search($this->makeFilters(['lat' => $lat, 'lng' => $lng]));

        foreach ($result->items() as $item) {
            $this->assertTrue($item->relationLoaded('saleHistory'));
            $this->assertCount(0, $item->getRelation('saleHistory'));
        }
    }

    #[Test]
    public function filters_by_house_number_prefix(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '12', 'street' => 'Oak Street']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '12A', 'street' => 'Oak Street']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '34', 'street' => 'Oak Street']);

        $result = $this->repository->search($this->makeFilters([
            'lat' => $lat, 'lng' => $lng, 'houseNumber' => '12',
        ]));

        $this->assertEquals(2, $result->total(), 'Prefix match should include 12 and 12A but not 34');
    }
}
