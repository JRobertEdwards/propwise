<?php

namespace App\Repositories;

use App\Models\Postcode;
use App\Repositories\Contracts\PostcodeRepositoryInterface;

class PostcodeRepository implements PostcodeRepositoryInterface
{
    public function findByPostcode(string $postcode): ?Postcode
    {
        return Postcode::find($postcode);
    }
}
