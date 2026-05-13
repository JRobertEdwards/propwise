<?php

namespace App\Repositories;

use App\Data\SearchFilters;
use App\Models\PropertySale;
use App\Repositories\Contracts\PropertySaleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertySaleRepository implements PropertySaleRepositoryInterface
{
    public function search(SearchFilters $filters): LengthAwarePaginator
    {
        return PropertySale::query()
            ->withinRadius($filters->lat, $filters->lng, $filters->radius)
            ->when($filters->propertyTypes, fn ($q, $types) => $q->ofType($types))
            ->when($filters->dateFrom, fn ($q, $from) => $q->soldBetween($from, $filters->dateTo ?? now()->toDateString()))
            ->with('epcCertificate')
            ->selectRaw(
                '*, ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_metres',
                [$filters->lng, $filters->lat]
            )
            ->orderBy('distance_metres')
            ->orderBy('sale_date', 'desc')
            ->paginate(25);
    }
}
