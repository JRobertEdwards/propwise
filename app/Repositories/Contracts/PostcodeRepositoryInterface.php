<?php

namespace App\Repositories\Contracts;

use App\Models\Postcode;

interface PostcodeRepositoryInterface
{
    public function findByPostcode(string $postcode): ?Postcode;
}
