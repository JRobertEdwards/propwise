<?php

namespace Tests\Unit\Services;

use App\Services\CrimeDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrimeDataServiceTest extends TestCase
{
    private const LAT = 51.5074;
    private const LNG = -0.1278;

    // ── getSummary ────────────────────────────────────────────────────────────

    #[Test]
    public function get_summary_returns_empty_when_all_months_fail(): void
    {
        Http::fake(['*crimes-street*' => Http::response(null, 500)]);

        $result = (new CrimeDataService())->getSummary(self::LAT, self::LNG);

        $this->assertEmpty($result);
    }

    #[Test]
    public function get_summary_aggregates_crime_counts_across_months(): void
    {
        Http::fake([
            '*crimes-street*' => Http::response([
                ['category' => 'violence-and-sexual-offences'],
                ['category' => 'burglary'],
                ['category' => 'violence-and-sexual-offences'],
            ]),
        ]);

        $result = (new CrimeDataService())->getSummary(self::LAT, self::LNG);

        $indexed = collect($result)->keyBy('category');
        $this->assertEquals(24, $indexed['violence-and-sexual-offences']['count']); // 2 × 12 months
        $this->assertEquals(12, $indexed['burglary']['count']);                      // 1 × 12 months
    }

    #[Test]
    public function get_summary_returns_sorted_by_count_descending(): void
    {
        Http::fake([
            '*crimes-street*' => Http::response([
                ['category' => 'burglary'],
                ['category' => 'violence-and-sexual-offences'],
                ['category' => 'violence-and-sexual-offences'],
            ]),
        ]);

        $result = (new CrimeDataService())->getSummary(self::LAT, self::LNG);

        $this->assertEquals('violence-and-sexual-offences', $result[0]['category']);
        $this->assertEquals('burglary', $result[1]['category']);
    }

    #[Test]
    public function get_summary_caches_successful_responses(): void
    {
        Http::fake(['*crimes-street*' => Http::response([['category' => 'burglary']])]);

        $service = new CrimeDataService();
        $service->getSummary(self::LAT, self::LNG);
        $service->getSummary(self::LAT, self::LNG);

        // 12 pool requests on first call; second call served from cache
        Http::assertSentCount(12);
    }

    #[Test]
    public function get_summary_does_not_cache_failed_responses(): void
    {
        Http::fake(['*crimes-street*' => Http::response(null, 500)]);

        $service = new CrimeDataService();
        $service->getSummary(self::LAT, self::LNG);
        $service->getSummary(self::LAT, self::LNG);

        // Both calls hit the API — failed responses are not cached
        Http::assertSentCount(24);
    }

    // ── getNeighbourhoodComparison ────────────────────────────────────────────

    #[Test]
    public function get_neighbourhood_comparison_returns_null_when_locate_fails(): void
    {
        Http::fake(['*locate-neighbourhood*' => Http::response(null, 500)]);

        $result = (new CrimeDataService())->getNeighbourhoodComparison(self::LAT, self::LNG);

        $this->assertNull($result);
    }

    #[Test]
    public function get_neighbourhood_comparison_returns_null_when_meta_fails(): void
    {
        Http::fake([
            '*locate-neighbourhood*' => Http::response(['force' => 'metropolitan', 'neighbourhood' => 'E05']),
            '*metropolitan/E05*'     => Http::response(null, 500),
            '*forces/metropolitan*'  => Http::response(['name' => 'Metropolitan Police']),
        ]);

        $result = (new CrimeDataService())->getNeighbourhoodComparison(self::LAT, self::LNG);

        $this->assertNull($result);
    }

    #[Test]
    public function get_neighbourhood_comparison_returns_null_when_boundary_fails(): void
    {
        Http::fake([
            '*locate-neighbourhood*'    => Http::response(['force' => 'metropolitan', 'neighbourhood' => 'E05']),
            '*metropolitan/E05/boundary*' => Http::response(null, 500),
            '*metropolitan/E05*'        => Http::response(['name' => 'Westminster']),
            '*forces/metropolitan*'     => Http::response(['name' => 'Metropolitan Police']),
        ]);

        $result = (new CrimeDataService())->getNeighbourhoodComparison(self::LAT, self::LNG);

        $this->assertNull($result);
    }

    #[Test]
    public function get_neighbourhood_comparison_returns_structured_data(): void
    {
        Http::fake([
            '*locate-neighbourhood*'      => Http::response(['force' => 'metropolitan', 'neighbourhood' => 'E05']),
            '*metropolitan/E05/boundary*' => Http::response([
                ['latitude' => '51.5', 'longitude' => '-0.1'],
                ['latitude' => '51.6', 'longitude' => '-0.2'],
            ]),
            '*metropolitan/E05*'          => Http::response(['name' => 'Westminster Central']),
            '*forces/metropolitan*'       => Http::response(['name' => 'Metropolitan Police']),
            '*crimes-street*'             => Http::response([
                ['category' => 'violence-and-sexual-offences'],
                ['category' => 'burglary'],
            ]),
        ]);

        $result = (new CrimeDataService())->getNeighbourhoodComparison(self::LAT, self::LNG);

        $this->assertNotNull($result);
        $this->assertEquals('Westminster Central', $result['neighbourhood']);
        $this->assertEquals('Metropolitan Police', $result['force']);
        $this->assertNotEmpty($result['counts']);
        $this->assertArrayHasKey('neighbourhood_count', $result['counts'][0]);
    }

    #[Test]
    public function locate_neighbourhood_does_not_cache_failures(): void
    {
        Http::fake(['*locate-neighbourhood*' => Http::response(null, 500)]);

        $service = new CrimeDataService();
        $service->getNeighbourhoodComparison(self::LAT, self::LNG);
        $service->getNeighbourhoodComparison(self::LAT, self::LNG);

        // Both calls must hit the API — null is never cached
        Http::assertSentCount(2);
    }

    #[Test]
    public function locate_neighbourhood_caches_successful_result(): void
    {
        Http::fake([
            '*locate-neighbourhood*'      => Http::response(['force' => 'metropolitan', 'neighbourhood' => 'E05']),
            '*metropolitan/E05/boundary*' => Http::response([['latitude' => '51.5', 'longitude' => '-0.1']]),
            '*metropolitan/E05*'          => Http::response(['name' => 'Westminster Central']),
            '*forces/metropolitan*'       => Http::response(['name' => 'Metropolitan Police']),
            '*crimes-street*'             => Http::response([]),
        ]);

        $service = new CrimeDataService();
        $service->getNeighbourhoodComparison(self::LAT, self::LNG);
        $service->getNeighbourhoodComparison(self::LAT, self::LNG);

        // locate-neighbourhood must only be called once across both invocations
        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'locate-neighbourhood')
        );
        $locateCalls = collect(Http::recorded())->filter(
            fn ($pair) => str_contains($pair[0]->url(), 'locate-neighbourhood')
        );
        $this->assertCount(1, $locateCalls);
    }
}
