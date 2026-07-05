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
        // Saat di belakang reverse-proxy (aaPanel, dll) yang tidak selalu
        // meneruskan header X-Forwarded-Proto dengan benar, paksa skema https
        // berdasarkan APP_URL saja supaya aset tidak pernah salah generate
        // sebagai http:// dan memicu Mixed Content di browser.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
