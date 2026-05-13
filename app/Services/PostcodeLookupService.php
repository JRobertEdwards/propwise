<?php

namespace App\Services;

use App\Models\Postcode;
use App\Repositories\Contracts\PostcodeRepositoryInterface;

class PostcodeLookupService
{
    public function __construct(private PostcodeRepositoryInterface $repository) {}

    public function lookup(string $postcode): ?Postcode
    {
        $normalised = strtoupper(str_replace(' ', '', $postcode));

        return $this->repository->findByPostcode($normalised);
    }
}
