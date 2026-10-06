<?php

namespace App\Providers;

use App\Sync\DemoHub;
use App\Sync\Hub;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound, not shared, so a test that points the config at a temp hub gets that hub.
        $this->app->bind(DemoHub::class, fn (): DemoHub => new DemoHub(base_path('demo'), config()->string('markboard.demo_path')));
        $this->app->bind(Hub::class, function (Application $app): Hub {
            $path = config('markboard.hub_path');

            return new Hub(rtrim(is_string($path) ? $path : $app->make(DemoHub::class)->ensure(), '/'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
