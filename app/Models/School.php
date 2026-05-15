<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    public $timestamps = false;

    protected $fillable = ['urn', 'name', 'type', 'phase', 'postcode', 'latitude', 'longitude'];

    protected $casts = [
        'urn' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusMiles): Builder
    {
        $radiusMetres = $radiusMiles * 1609.34;

        return $query
            ->selectRaw('*, ST_Distance(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_metres', [$lng, $lat])
            ->whereRaw('ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radiusMetres])
            ->orderBy('distance_metres');
    }
}
