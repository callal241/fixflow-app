<?php

namespace App\Providers;

use App\Payments\PaymentProviderRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enable strict mode for Eloquent models to catch N+1 queries and other issues
        Model::shouldBeStrict();
    }
}
