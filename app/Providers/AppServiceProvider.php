<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        $envVersionPath = base_path('.env.version');
        if (file_exists($envVersionPath)) {
            foreach (file($envVersionPath) as $line) {
                putenv(trim($line));
            }
        }

        config(['app.version' => getenv('APP_VERSION') ?: config('app.version') ]);
    }
}
