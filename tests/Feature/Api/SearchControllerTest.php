<?php

namespace Tests\Feature\Api;

use App\Models\EpcCertificate;
use App\Models\Postcode;
use App\Models\PropertySale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_404_for_unknown_postcode(): void
    {
        $this->getJson('/api/search?postcode=SW1A9ZZ')
            ->assertStatus(404)
            ->assertJson(['message' => 'Postcode not found']);
    }

    #[Test]
    public function rejects_invalid_postcode_format(): void
    {
        $this->getJson('/api/search?postcode=NOTVALID')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function requires_postcode_parameter(): void
    {
        $this->getJson('/api/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function validates_radius_values(): void
    {
        $this->getJson('/api/search?postcode=SW1A1AA&radius=99')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['radius']);
    }

    #[Test]
    public function validates_property_type_values(): void
    {
        $this->getJson('/api/search?postcode=SW1A1AA&property_type[]=X')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['property_type.0']);
    }

    #[Test]
    public function returns_paginated_results_for_valid_postcode(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => $lat,
            'longitude' => $lng,
        ]);

        PropertySale::factory()
            ->count(3)
            ->forPostcode('SW1A1AA', $lat, $lng)
            ->create();

        $this->getJson('/api/search?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'address', 'sold_price', 'sale_date', 'property_type', 'distance_metres']],
                'meta' => ['total', 'per_page', 'current_page'],
            ])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function filters_by_property_type(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['property_type' => 'T']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['property_type' => 'D']);

        $this->getJson('/api/search?postcode=SW1A1AA&property_type[]=T')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.property_type', 'T');
    }

    #[Test]
    public function results_include_price_per_sqm_when_epc_matched(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);

        $epc = EpcCertificate::factory()->create(['total_floor_area' => 80.0]);

        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create([
            'price'                => 200000,
            'epc_certificate_id'   => $epc->id,
            'epc_match_confidence' => 'exact',
        ]);

        $this->getJson('/api/search?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonPath('data.0.price_per_sqm', 2500)
            ->assertJsonPath('data.0.epc_match_confidence', 'exact');
    }

    #[Test]
    public function results_include_sale_history_and_count(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);

        $address = ['paon' => '12', 'street' => 'Oak Street', 'saon' => null];

        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create($address + [
            'sale_date' => '2024-05-20', 'price' => 485000, 'property_type' => 'S',
        ]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create($address + [
            'sale_date' => '2018-03-10', 'price' => 310000, 'property_type' => 'T',
        ]);

        $this->getJson('/api/search?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sale_count', 2)
            ->assertJsonPath('data.0.sold_price', 485000)
            ->assertJsonCount(1, 'data.0.sale_history')
            ->assertJsonPath('data.0.sale_history.0.sold_price', 310000)
            ->assertJsonPath('data.0.sale_history.0.property_type', 'T');
    }

    #[Test]
    public function filters_by_house_number(): void
    {
        $lat = 51.5074;
        $lng = -0.1278;

        Postcode::factory()->create(['postcode' => 'SW1A1AA', 'latitude' => $lat, 'longitude' => $lng]);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '12', 'street' => 'Oak Street']);
        PropertySale::factory()->forPostcode('SW1A1AA', $lat, $lng)->create(['paon' => '34', 'street' => 'Oak Street']);

        $this->getJson('/api/search?postcode=SW1A1AA&house_number=12')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.address', '12 Oak Street');
    }
}
