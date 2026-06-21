<?php

namespace Tests\Feature;

use App\Data\SearchFilters;
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

    private float $lat = 51.5074;
    private float $lng = -0.1278;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PropertySaleRepository();
        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $this->lat, 'longitude' => $this->lng]);
    }

    private function makeFilters(array $overrides = []): SearchFilters
    {
        return new SearchFilters(
            lat: $overrides['lat'] ?? $this->lat,
            lng: $overrides['lng'] ?? $this->lng,
            radius: $overrides['radius'] ?? 1.0,
            propertyTypes: $overrides['propertyTypes'] ?? null,
            dateFrom: $overrides['dateFrom'] ?? null,
            dateTo: $overrides['dateTo'] ?? null,
            houseNumber: $overrides['houseNumber'] ?? null,
        );
    }

    private function sale(array $attrs = []): PropertySale
    {
        return PropertySale::factory()->forPostcode('SW1A1AA', $this->lat, $this->lng)->create($attrs);
    }

    #[Test]
    public function single_sale_property_has_sale_count_of_one_and_empty_history(): void
    {
        $this->sale(['paon' => '5', 'street' => 'Elm Road', 'saon' => null]);

        $result = $this->repository->search($this->makeFilters());

        $this->assertEquals(1, $result->total());
        $item = $result->items()[0];
        $this->assertEquals(1, (int) $item->sale_count);
        $this->assertTrue($item->relationLoaded('saleHistory'));
        $this->assertCount(0, $item->getRelation('saleHistory'));
    }

    #[Test]
    public function null_and_empty_string_saon_are_treated_as_same_property(): void
    {
        $this->sale([
            'paon' => '10', 'street' => 'Pine Ave', 'saon' => null,
            'sale_date' => '2024-01-01', 'price' => 400000,
        ]);
        $this->sale([
            'paon' => '10', 'street' => 'Pine Ave', 'saon' => '',
            'sale_date' => '2020-06-15', 'price' => 300000,
        ]);

        $result = $this->repository->search($this->makeFilters());

        $this->assertEquals(1, $result->total(), 'null saon and empty-string saon should dedupe to one result');
        $item = $result->items()[0];
        $this->assertEquals(2, (int) $item->sale_count);
        $this->assertEquals(400000, $item->price, 'Most recent sale should be shown');
        $this->assertCount(1, $item->getRelation('saleHistory'));
        $this->assertEquals(300000, $item->getRelation('saleHistory')->first()->price);
    }

    #[Test]
    public function case_and_whitespace_differences_in_address_are_treated_as_same_property(): void
    {
        $this->sale([
            'paon' => 'FLAT 3 ', 'street' => 'HIGH STREET', 'saon' => null,
            'sale_date' => '2023-03-01', 'price' => 250000,
        ]);
        $this->sale([
            'paon' => 'flat 3', 'street' => 'high street', 'saon' => null,
            'sale_date' => '2019-07-20', 'price' => 180000,
        ]);

        $result = $this->repository->search($this->makeFilters());

        $this->assertEquals(1, $result->total(), 'Case and whitespace variants should dedupe to one result');
        $item = $result->items()[0];
        $this->assertEquals(2, (int) $item->sale_count);
        $this->assertEquals(250000, $item->price, 'Most recent sale should be shown');
        $this->assertCount(1, $item->getRelation('saleHistory'));
        $this->assertEquals(180000, $item->getRelation('saleHistory')->first()->price);
    }

    #[Test]
    public function multi_sale_history_is_ordered_most_recent_first(): void
    {
        $address = ['paon' => '7', 'street' => 'Maple Close', 'saon' => null];

        $this->sale($address + ['sale_date' => '2010-01-01', 'price' => 100000]);
        $this->sale($address + ['sale_date' => '2016-06-01', 'price' => 200000]);
        $this->sale($address + ['sale_date' => '2023-11-01', 'price' => 350000]);

        $result = $this->repository->search($this->makeFilters());

        $this->assertEquals(1, $result->total());
        $item = $result->items()[0];
        $this->assertEquals(350000, $item->price);
        $history = $item->getRelation('saleHistory');
        $this->assertCount(2, $history);
        $this->assertEquals(200000, $history[0]->price);
        $this->assertEquals(100000, $history[1]->price);
    }

    #[Test]
    public function pagination_returns_correct_total_across_pages(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->sale(['paon' => (string) $i, 'street' => 'Test Street', 'saon' => null]);
        }

        $result = $this->repository->search($this->makeFilters());

        $this->assertEquals(30, $result->total());
        $this->assertEquals(25, $result->perPage());
        $this->assertCount(25, $result->items());
    }
}
