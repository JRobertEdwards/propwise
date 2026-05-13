<?php

namespace App\Providers;

use App\Repositories\Contracts\PostcodeRepositoryInterface;
use App\Repositories\Contracts\PropertySaleRepositoryInterface;
use App\Repositories\PostcodeRepository;
use App\Repositories\PropertySaleRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostcodeRepositoryInterface::class, PostcodeRepository::class);
        $this->app->bind(PropertySaleRepositoryInterface::class, PropertySaleRepository::class);
    }

    public function boot(): void {}
}
