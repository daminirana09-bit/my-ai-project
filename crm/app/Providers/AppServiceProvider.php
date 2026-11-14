<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Our StartSession does not remember downloads/PDFs/images as the "previous page".
        $this->app->singleton(\Illuminate\Session\Middleware\StartSession::class, function ($app) {
            return new \App\Http\Middleware\StartSession($app->make(\Illuminate\Session\SessionManager::class), function () use ($app) {
                return $app->make(\Illuminate\Contracts\Cache\Factory::class);
            });
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
