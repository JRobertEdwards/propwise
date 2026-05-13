<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySale extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'price', 'sale_date', 'postcode', 'property_type',
        'new_build', 'estate_type', 'paon', 'saon', 'street', 'locality',
        'town_city', 'district', 'county', 'epc_certificate_id', 'epc_match_confidence',
    ];

    protected $casts = [
        'price' => 'integer',
        'new_build' => 'boolean',
        'sale_date' => 'date',
    ];

    public function epcCertificate()
    {
        return $this->belongsTo(EpcCertificate::class);
    }

    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusMiles): Builder
    {
        $radiusMetres = $radiusMiles * 1609.34;

        return $query->whereRaw(
            'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$lng, $lat, $radiusMetres]
        );
    }

    public function scopeOfType(Builder $query, string|array $type): Builder
    {
        return $query->whereIn('property_type', (array) $type);
    }

    public function scopeSoldBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('sale_date', [$from, $to]);
    }

    public function scopeWithEpc(Builder $query): Builder
    {
        return $query->whereNotNull('epc_certificate_id');
    }

    public function getPricePerSqmAttribute(): ?float
    {
        $area = $this->epcCertificate?->total_floor_area;

        if (!$area || $area <= 0) {
            return null;
        }

        return round($this->price / $area, 2);
    }
}
