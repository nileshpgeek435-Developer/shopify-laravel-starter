<?php

namespace App\Providers;

use App\Http\Middleware\IframeProtection as AppIframeProtection;
use Illuminate\Support\ServiceProvider;
use Osiset\ShopifyApp\Http\Middleware\IframeProtection as PackageIframeProtection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Package middleware caches Eloquent User models in Redis; after User
        // changes that causes "__PHP_Incomplete_Class" on unserialize.
        // Swap in our middleware that caches the shop domain string only.
        $this->app->bind(PackageIframeProtection::class, AppIframeProtection::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
