<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CrimeDataService
{
    private const API_BASE = 'https://data.police.uk/api';
    private const MONTHS = 12;
    private const CACHE_TTL = 86400;
    private const META_TTL = 604800; // 7 days — boundary and names rarely change

    public function getSummary(float $lat, float $lng): array
    {
        $months = array_map(
            fn($i) => Carbon::now()->subMonths($i)->format('Y-m'),
            range(1, self::MONTHS)
        );

        $crimeLists = [];
        $uncached = [];

        foreach ($months as $month) {
            $key = "crime:{$lat}:{$lng}:{$month}";
            $cached = Cache::get($key);
            if ($cached !== null) {
                $crimeLists[$month] = $cached;
            } else {
                $uncached[$month] = $key;
            }
        }

        if (!empty($uncached)) {
            $responses = Http::pool(function (Pool $pool) use ($lat, $lng, $uncached) {
                return array_map(
                    fn($month) => $pool->as($month)->timeout(10)->get(self::API_BASE . '/crimes-street/all-crime', [
                        'lat' => $lat,
                        'lng' => $lng,
                        'date' => $month,
                    ]),
                    array_keys($uncached)
                );
            });

            foreach ($uncached as $month => $key) {
                $response = $responses[$month] ?? null;
                if ($response instanceof Response && $response->successful()) {
                    $data = $response->json() ?? [];
                    Cache::put($key, $data, self::CACHE_TTL);
                    $crimeLists[$month] = $data;
                }
            }
        }

        $counts = [];
        foreach ($crimeLists as $crimes) {
            foreach ($crimes as $crime) {
                $category = $crime['category'] ?? 'unknown';
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_map(
            fn($category, $count) => ['category' => $category, 'count' => $count],
            array_keys($counts),
            array_values($counts)
        );
    }

    public function getNeighbourhoodComparison(float $lat, float $lng): ?array
    {
        $location = $this->locateNeighbourhood($lat, $lng);
        if (!$location) {
            return null;
        }

        ['force' => $forceId, 'neighbourhood' => $neighbourhoodId] = $location;

        $meta = $this->getNeighbourhoodMeta($forceId, $neighbourhoodId);
        if (!$meta) {
            return null;
        }

        $polygon = $this->getNeighbourhoodBoundary($forceId, $neighbourhoodId);
        if (!$polygon) {
            return null;
        }

        $counts = $this->fetchNeighbourhoodCrimeCounts($forceId, $neighbourhoodId, $polygon);

        arsort($counts);

        return [
            'neighbourhood' => $meta['neighbourhood'],
            'force' => $meta['force'],
            'counts' => array_map(
                fn($category, $count) => ['category' => $category, 'neighbourhood_count' => $count],
                array_keys($counts),
                array_values($counts)
            ),
        ];
    }

    private function locateNeighbourhood(float $lat, float $lng): ?array
    {
        $cacheKey = "neighbourhood:location:{$lat}:{$lng}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = Http::timeout(10)->get(self::API_BASE . '/locate-neighbourhood', [
            'q' => "{$lat},{$lng}",
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        Cache::put($cacheKey, $data, self::META_TTL);
        return $data;
    }

    private function getNeighbourhoodMeta(string $forceId, string $neighbourhoodId): ?array
    {
        $cacheKey = "neighbourhood:meta:{$forceId}:{$neighbourhoodId}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        [$nRes, $fRes] = [
            Http::timeout(10)->get(self::API_BASE . "/{$forceId}/{$neighbourhoodId}"),
            Http::timeout(10)->get(self::API_BASE . "/forces/{$forceId}"),
        ];

        if (!$nRes->successful() || !$fRes->successful()) {
            return null;
        }

        $data = [
            'neighbourhood' => $nRes->json('name'),
            'force' => $fRes->json('name'),
        ];

        Cache::put($cacheKey, $data, self::META_TTL);
        return $data;
    }

    private function getNeighbourhoodBoundary(string $forceId, string $neighbourhoodId): ?string
    {
        $cacheKey = "neighbourhood:boundary:{$forceId}:{$neighbourhoodId}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = Http::timeout(10)->get(self::API_BASE . "/{$forceId}/{$neighbourhoodId}/boundary");

        if (!$response->successful()) {
            return null;
        }

        $data = implode(':', array_map(
            fn($p) => "{$p['latitude']},{$p['longitude']}",
            $response->json()
        ));

        Cache::put($cacheKey, $data, self::META_TTL);
        return $data;
    }

    private function fetchNeighbourhoodCrimeCounts(string $forceId, string $neighbourhoodId, string $polygon): array
    {
        $months = array_map(
            fn($i) => Carbon::now()->subMonths($i)->format('Y-m'),
            range(1, self::MONTHS)
        );

        $crimeLists = [];
        $uncached = [];

        foreach ($months as $month) {
            $key = "neighbourhood:crimes:{$forceId}:{$neighbourhoodId}:{$month}";
            $cached = Cache::get($key);
            if ($cached !== null) {
                $crimeLists[$month] = $cached;
            } else {
                $uncached[$month] = $key;
            }
        }

        if (!empty($uncached)) {
            $responses = Http::pool(function (Pool $pool) use ($polygon, $uncached) {
                return array_map(
                    fn($month) => $pool->as($month)->timeout(30)->asForm()->post(
                        self::API_BASE . '/crimes-street/all-crime',
                        ['poly' => $polygon, 'date' => $month]
                    ),
                    array_keys($uncached)
                );
            });

            foreach ($uncached as $month => $key) {
                $response = $responses[$month] ?? null;
                if ($response instanceof Response && $response->successful()) {
                    $data = $response->json() ?? [];
                    Cache::put($key, $data, self::CACHE_TTL);
                    $crimeLists[$month] = $data;
                }
            }
        }

        $counts = [];
        foreach ($crimeLists as $crimes) {
            foreach ((array) $crimes as $crime) {
                $category = $crime['category'] ?? 'unknown';
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
