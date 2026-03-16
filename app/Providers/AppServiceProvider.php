<?php

namespace App\Providers;

use App\Contracts\DeliveryServiceInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Contracts\OrderServiceInterface;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use App\Services\UberDirectService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OrderRepositoryInterface::class,
            OrderRepository::class,
        );

        $this->app->bind(
            DeliveryServiceInterface::class,
            UberDirectService::class,
        );

        $this->app->bind(
            OrderServiceInterface::class,
            OrderService::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}