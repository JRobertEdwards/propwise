<?php

namespace App\Services;

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
        $counts = [];

        for ($i = 1; $i <= self::MONTHS; $i++) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $cacheKey = "crime:{$lat}:{$lng}:{$month}";

            $crimes = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lat, $lng, $month) {
                $response = Http::timeout(10)->get(self::API_BASE . '/crimes-street/all-crime', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'date' => $month,
                ]);

                return $response->successful() ? $response->json() : [];
            });

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

        return Cache::remember($cacheKey, self::META_TTL, function () use ($lat, $lng) {
            $response = Http::timeout(10)->get(self::API_BASE . '/locate-neighbourhood', [
                'q' => "{$lat},{$lng}",
            ]);

            return $response->successful() ? $response->json() : null;
        });
    }

    private function getNeighbourhoodMeta(string $forceId, string $neighbourhoodId): ?array
    {
        $cacheKey = "neighbourhood:meta:{$forceId}:{$neighbourhoodId}";

        return Cache::remember($cacheKey, self::META_TTL, function () use ($forceId, $neighbourhoodId) {
            [$nRes, $fRes] = [
                Http::timeout(10)->get(self::API_BASE . "/{$forceId}/{$neighbourhoodId}"),
                Http::timeout(10)->get(self::API_BASE . "/forces/{$forceId}"),
            ];

            if (!$nRes->successful() || !$fRes->successful()) {
                return null;
            }

            return [
                'neighbourhood' => $nRes->json('name'),
                'force' => $fRes->json('name'),
            ];
        });
    }

    private function getNeighbourhoodBoundary(string $forceId, string $neighbourhoodId): ?string
    {
        $cacheKey = "neighbourhood:boundary:{$forceId}:{$neighbourhoodId}";

        return Cache::remember($cacheKey, self::META_TTL, function () use ($forceId, $neighbourhoodId) {
            $response = Http::timeout(10)->get(self::API_BASE . "/{$forceId}/{$neighbourhoodId}/boundary");

            if (!$response->successful()) {
                return null;
            }

            return implode(':', array_map(
                fn($p) => "{$p['latitude']},{$p['longitude']}",
                $response->json()
            ));
        });
    }

    private function fetchNeighbourhoodCrimeCounts(string $forceId, string $neighbourhoodId, string $polygon): array
    {
        $counts = [];

        for ($i = 1; $i <= self::MONTHS; $i++) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $cacheKey = "neighbourhood:crimes:{$forceId}:{$neighbourhoodId}:{$month}";

            $crimes = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($polygon, $month) {
                $response = Http::timeout(30)->asForm()->post(self::API_BASE . '/crimes-street/all-crime', [
                    'poly' => $polygon,
                    'date' => $month,
                ]);

                return $response->successful() ? $response->json() : [];
            });

            foreach ((array) $crimes as $crime) {
                $category = $crime['category'] ?? 'unknown';
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
