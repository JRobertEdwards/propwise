<?php

namespace Tests\Feature\Api;

use App\Models\Postcode;
use App\Repositories\Contracts\SchoolRepositoryInterface;
use App\Services\CrimeDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AreaSummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function requires_postcode_parameter(): void
    {
        $this->getJson('/api/area-summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function rejects_invalid_postcode_format(): void
    {
        $this->getJson('/api/area-summary?postcode=NOTVALID')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode']);
    }

    #[Test]
    public function returns_404_for_unknown_postcode(): void
    {
        $this->getJson('/api/area-summary?postcode=SW1A9ZZ')
            ->assertStatus(404)
            ->assertJson(['message' => 'Postcode not found']);
    }

    #[Test]
    public function returns_area_summary_for_valid_postcode(): void
    {
        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->mock(CrimeDataService::class)
            ->shouldReceive('getSummary')
            ->once()
            ->andReturn([
                ['category' => 'violence-and-sexual-offences', 'count' => 12],
                ['category' => 'anti-social-behaviour', 'count' => 8],
            ]);

        $this->mock(SchoolRepositoryInterface::class)
            ->shouldReceive('findNearby')
            ->once()
            ->andReturn(collect());

        $this->getJson('/api/area-summary?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonStructure(['postcode', 'crime', 'schools'])
            ->assertJsonPath('postcode', 'SW1A1AA')
            ->assertJsonCount(2, 'crime');
    }

    #[Test]
    public function returns_empty_crime_when_api_unavailable(): void
    {
        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->mock(CrimeDataService::class)
            ->shouldReceive('getSummary')
            ->once()
            ->andReturn([]);

        $this->mock(SchoolRepositoryInterface::class)
            ->shouldReceive('findNearby')
            ->once()
            ->andReturn(collect());

        $this->getJson('/api/area-summary?postcode=SW1A1AA')
            ->assertStatus(200)
            ->assertJsonPath('crime', []);
    }

    #[Test]
    public function normalises_mixed_case_spaced_postcode(): void
    {
        Postcode::factory()->create([
            'postcode'  => 'SW1A1AA',
            'latitude'  => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->mock(CrimeDataService::class)
            ->shouldReceive('getSummary')
            ->once()
            ->andReturn([]);

        $this->mock(SchoolRepositoryInterface::class)
            ->shouldReceive('findNearby')
            ->once()
            ->andReturn(collect());

        $this->getJson('/api/area-summary?postcode=sw1a+1aa')
            ->assertStatus(200)
            ->assertJsonPath('postcode', 'SW1A1AA');
    }
}
