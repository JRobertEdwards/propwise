<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface SchoolRepositoryInterface
{
    public function findNearby(float $lat, float $lng, float $radiusMiles, int $limit = 10): Collection;
}
