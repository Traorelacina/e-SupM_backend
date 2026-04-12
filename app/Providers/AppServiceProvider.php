<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
// use Illuminate\Pagination\LengthAwarePaginator; // À supprimer

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoyaltyService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(LoyaltyService::class),
                $app->make(CartService::class)
            );
        });

        // SUPPRIMEZ COMPLÈTEMENT CE BLOC
        // if (class_exists(LengthAwarePaginator::class)) {
        //     LengthAwarePaginator::macro('toJsonResponse', function () {
        //         return response()->json([...]);
        //     });
        // }
    }

    public function boot(): void
    {
        Model::shouldBeStrict(!$this->app->isProduction());
        JsonResource::withoutWrapping();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        Gate::define('admin', fn($user) => $user->isAdmin());
        Gate::define('manage-products', fn($user) => $user->isAdmin() || $user->isPreparateur());
        Gate::define('manage-orders', fn($user) => $user->isAdmin() || $user->isPreparateur() || $user->isLivreur());
    }
}