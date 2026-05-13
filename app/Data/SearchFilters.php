<?php

namespace App\Data;

use App\Http\Requests\SearchRequest;

readonly class SearchFilters
{
    public function __construct(
        public float $lat,
        public float $lng,
        public float $radius,
        public ?array $propertyTypes = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    public static function fromRequest(SearchRequest $request, float $lat, float $lng): self
    {
        return new self(
            lat: $lat,
            lng: $lng,
            radius: $request->radius(),
            propertyTypes: $request->input('property_type'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
        );
    }
}
