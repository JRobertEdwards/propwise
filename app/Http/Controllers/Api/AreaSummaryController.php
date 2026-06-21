<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostcodeRequest;
use App\Http\Resources\SchoolResource;
use App\Repositories\Contracts\SchoolRepositoryInterface;
use App\Services\CrimeDataService;
use App\Services\PostcodeLookupService;
use Illuminate\Http\JsonResponse;

class AreaSummaryController extends Controller
{
    public function __construct(
        private PostcodeLookupService $postcodeLookup,
        private CrimeDataService $crimeData,
        private SchoolRepositoryInterface $schools,
    ) {}

    public function __invoke(PostcodeRequest $request): JsonResponse
    {
        $postcode = $this->postcodeLookup->lookup($request->input('postcode'));

        if (!$postcode) {
            return response()->json(['message' => 'Postcode not found'], 404);
        }

        $crime = $this->crimeData->getSummary($postcode->latitude, $postcode->longitude);
        $schools = $this->schools->findNearby($postcode->latitude, $postcode->longitude, 1.0);

        return response()->json([
            'postcode' => $postcode->postcode,
            'crime' => $crime,
            'schools' => SchoolResource::collection($schools),
        ]);
    }
}
