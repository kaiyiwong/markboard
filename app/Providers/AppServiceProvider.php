<?php

namespace App\Providers;

use App\Sync\Hub;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound, not shared, so a test that points the config at a temp hub gets that hub.
        $this->app->bind(Hub::class, fn (): Hub => new Hub(rtrim(config()->string('markboard.hub_path'), '/')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
