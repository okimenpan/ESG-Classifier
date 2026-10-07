<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Generate https:// links when the app is served over HTTPS (http links from an https page
        // are blocked by the browser, e.g. the download button silently does nothing)
        URL::forceHttps(str_starts_with((string) config('app.url'), 'https://'));
    }
}
