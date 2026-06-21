<?php

namespace Tests\Feature\Api;

use App\Models\Postcode;
use App\Services\CrimeDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrimeComparisonControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function requires_postcode_parameter(): void
    {
        $this->getJson('/api/crime-comparison')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function rejects_invalid_postcode_format(): void
    {
        $this->getJson('/api/crime-comparison?postcode=NOTVALID')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function returns_404_for_unknown_postcode(): void
    {
        $this->getJson('/api/crime-comparison?postcode=SW1A9ZZ')
            ->assertStatus(404)
            ->assertJson(['message' => 'Postcode not found']);
    }

    #[Test]
    public function returns_503_when_neighbourhood_data_unavailable(): void
    {
        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->mock(CrimeDataService::class)
            ->shouldReceive('getNeighbourhoodComparison')
            ->once()
            ->andReturn(null);

        $this->getJson('/api/crime-comparison?postcode=SW1A1AA')
            ->assertStatus(503)
            ->assertJson(['message' => 'Neighbourhood data unavailable']);
    }

    #[Test]
    public function returns_comparison_data_for_valid_postcode(): void
    {
        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->mock(CrimeDataService::class)
            ->shouldReceive('getNeighbourhoodComparison')
            ->once()
            ->andReturn([
                'neighbourhood' => 'Westminster',
                'force'         => 'Metropolitan Police',
                'counts'        => [
                    ['category' => 'violence-and-sexual-offences', 'neighbourhood_count' => 25],
                ],
            ]);

        $this->getJson('/api/crime-comparison?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonStructure(['neighbourhood', 'force', 'counts'])
            ->assertJsonPath('neighbourhood', 'Westminster')
            ->assertJsonPath('force', 'Metropolitan Police');
    }
}
