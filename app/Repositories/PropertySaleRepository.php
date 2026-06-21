<?php

namespace App\Repositories;

use App\Data\SearchFilters;
use App\Models\PropertySale;
use App\Repositories\Contracts\PropertySaleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PropertySaleRepository implements PropertySaleRepositoryInterface
{
    public function search(SearchFilters $filters): LengthAwarePaginator
    {
        $inner = PropertySale::query()
            ->withinRadius($filters->lat, $filters->lng, $filters->radius)
            ->when($filters->propertyTypes, fn ($q, $types) => $q->ofType($types))
            ->when($filters->dateFrom, fn ($q, $from) => $q->soldBetween($from, $filters->dateTo ?? now()->toDateString()))
            ->when($filters->houseNumber, fn ($q, $hn) => $q->whereRaw('paon ILIKE ?', [$hn . '%']))
            ->selectRaw(
                "property_sales.*,
                 ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(COALESCE(paon,''))), UPPER(TRIM(COALESCE(saon,''))), UPPER(TRIM(COALESCE(street,''))), UPPER(TRIM(postcode)) ORDER BY sale_date DESC) AS rn,
                 COUNT(*) OVER (PARTITION BY UPPER(TRIM(COALESCE(paon,''))), UPPER(TRIM(COALESCE(saon,''))), UPPER(TRIM(COALESCE(street,''))), UPPER(TRIM(postcode))) AS sale_count,
                 ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_metres",
                [$filters->lng, $filters->lat]
            );

        $paginator = PropertySale::query()
            ->fromSub($inner, 'property_sales')
            ->where('rn', 1)
            ->with('epcCertificate')
            ->orderBy('distance_metres')
            ->orderBy('sale_date', 'desc')
            ->paginate(25);

        $this->attachSaleHistory($paginator->getCollection());

        return $paginator;
    }

    private function attachSaleHistory(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        // Pre-set empty history for all; multi-sale ones are overwritten below
        $items->each(fn ($item) => $item->setRelation('saleHistory', collect()));

        $multiSale = $items->filter(fn ($item) => (int) ($item->sale_count ?? 1) > 1);

        if ($multiSale->isEmpty()) {
            return;
        }

        $query = PropertySale::query()->where(function ($outer) use ($multiSale) {
            foreach ($multiSale as $item) {
                $outer->orWhere(function ($q) use ($item) {
                    $q->whereRaw("UPPER(TRIM(COALESCE(paon,''))) = ?", [strtoupper(trim($item->paon ?? ''))])
                      ->whereRaw("UPPER(TRIM(COALESCE(saon,''))) = ?", [strtoupper(trim($item->saon ?? ''))])
                      ->whereRaw("UPPER(TRIM(COALESCE(street,''))) = ?", [strtoupper(trim($item->street ?? ''))])
                      ->whereRaw('UPPER(TRIM(postcode)) = ?', [strtoupper(trim($item->postcode))]);
                });
            }
        })->orderBy('sale_date', 'desc');

        $byKey = $query->get()->groupBy(fn ($row) => $this->tupleKey($row));

        foreach ($multiSale as $item) {
            $history = ($byKey->get($this->tupleKey($item)) ?? collect())
                ->reject(fn ($row) => (int) $row->id === (int) $item->id)
                ->values();

            $item->setRelation('saleHistory', $history);
        }
    }

    private function tupleKey(PropertySale $row): string
    {
        return strtoupper(trim($row->paon ?? ''))
            . '|' . strtoupper(trim($row->saon ?? ''))
            . '|' . strtoupper(trim($row->street ?? ''))
            . '|' . strtoupper(trim($row->postcode));
    }
}
