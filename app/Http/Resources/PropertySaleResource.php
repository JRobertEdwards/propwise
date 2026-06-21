<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertySaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'address'              => trim("{$this->paon} {$this->street}"),
            'town'                 => $this->town_city,
            'postcode'             => $this->postcode,
            'sold_price'           => $this->price,
            'sale_date'            => $this->sale_date->toDateString(),
            'property_type'        => $this->property_type,
            'new_build'            => $this->new_build,
            'estate_type'          => $this->estate_type,
            'floor_area_sqm'       => $this->epcCertificate?->total_floor_area,
            'price_per_sqm'        => $this->price_per_sqm,
            'epc_match_confidence' => $this->epc_match_confidence,
            'distance_metres'      => isset($this->resource->distance_metres)
                ? (int) round($this->resource->distance_metres)
                : null,
            'sale_count'           => (int) ($this->resource->sale_count ?? 1),
            'sale_history'         => collect($this->saleHistory ?? [])
                ->map(fn ($h) => [
                    'sale_date'     => $h->sale_date->toDateString(),
                    'sold_price'    => (int) $h->price,
                    'property_type' => $h->property_type,
                    'new_build'     => (bool) $h->new_build,
                    'estate_type'   => $h->estate_type,
                ])->values(),
        ];
    }
}
