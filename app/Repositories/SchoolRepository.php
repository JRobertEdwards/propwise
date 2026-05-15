<?php

namespace App\Repositories;

use App\Models\School;
use App\Repositories\Contracts\SchoolRepositoryInterface;
use Illuminate\Support\Collection;

class SchoolRepository implements SchoolRepositoryInterface
{
    public function findNearby(float $lat, float $lng, float $radiusMiles, int $limit = 10): Collection
    {
        return School::nearby($lat, $lng, $radiusMiles)->limit($limit)->get();
    }
}
