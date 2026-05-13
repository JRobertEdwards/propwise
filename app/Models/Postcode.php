<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Postcode extends Model
{
    use HasFactory;
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'postcode';

    protected $fillable = ['postcode', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function sales()
    {
        return $this->hasMany(PropertySale::class, 'postcode', 'postcode');
    }

    public function scopeNear(Builder $query, float $lat, float $lng, float $radiusMiles): Builder
    {
        $radiusMetres = $radiusMiles * 1609.34;

        return $query->whereRaw(
            'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$lng, $lat, $radiusMetres]
        );
    }
}
