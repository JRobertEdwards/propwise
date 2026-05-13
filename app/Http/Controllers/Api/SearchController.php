<?php

namespace App\Http\Controllers\Api;

use App\Data\SearchFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\PropertySaleResource;
use App\Repositories\Contracts\PropertySaleRepositoryInterface;
use App\Services\PostcodeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    public function __construct(
        private PostcodeLookupService $postcodeLookup,
        private PropertySaleRepositoryInterface $repository,
    ) {}

    public function __invoke(SearchRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $postcode = $this->postcodeLookup->lookup($request->input('postcode'));

        if (!$postcode) {
            return response()->json(['message' => 'Postcode not found'], 404);
        }

        $filters = SearchFilters::fromRequest($request, $postcode->latitude, $postcode->longitude);

        $sales = $this->repository->search($filters);

        return PropertySaleResource::collection($sales);
    }
}
