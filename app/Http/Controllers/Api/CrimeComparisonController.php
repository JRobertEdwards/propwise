<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostcodeRequest;
use App\Services\CrimeDataService;
use App\Services\PostcodeLookupService;
use Illuminate\Http\JsonResponse;

class CrimeComparisonController extends Controller
{
    public function __construct(
        private PostcodeLookupService $postcodeLookup,
        private CrimeDataService $crimeData,
    ) {}

    public function __invoke(PostcodeRequest $request): JsonResponse
    {
        $postcode = $this->postcodeLookup->lookup($request->input('postcode'));

        if (!$postcode) {
            return response()->json(['message' => 'Postcode not found'], 404);
        }

        $comparison = $this->crimeData->getNeighbourhoodComparison($postcode->latitude, $postcode->longitude);

        if (!$comparison) {
            return response()->json(['message' => 'Neighbourhood data unavailable'], 503);
        }

        return response()->json($comparison);
    }
}
