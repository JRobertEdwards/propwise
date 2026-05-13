<?php

namespace App\Repositories\Contracts;

use App\Data\SearchFilters;
use Illuminate\Pagination\LengthAwarePaginator;

interface PropertySaleRepositoryInterface
{
    public function search(SearchFilters $filters): LengthAwarePaginator;
}
