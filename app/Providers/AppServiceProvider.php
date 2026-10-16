<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->defineRateLimiters();
    }

    /**
     * Mendaftarkan rate limiter untuk seluruh aplikasi.
     */
    private function defineRateLimiters(): void
    {
        // Membatasi permintaan chat AI: 20 pesan per menit per user/IP.
        RateLimiter::for('ai-chat', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(20)->by($key);
        });
    }
}
