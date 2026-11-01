<?php

namespace App\Providers;

use App\Http\Middleware\ApplyOrganizationScope;
use App\Http\Middleware\ApplyPlatformCompanyScope;
use App\Http\Middleware\EnterClientForSupport;
use App\Http\Middleware\RequirePlatformAdmin;
use App\Http\Middleware\ResolveClientSession;
use App\Models\Scopes\OrganizationScope;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        $this->loadMigrationsFrom(database_path('migrations/platform'));
        // Session storage must never follow the client's business-data connection.
        if (config('session.driver') === 'database' && ! config('session.connection')) {
            config(['session.connection' => config('tenancy.central_connection')]);
        }
        foreach (['cache.stores.database.connection', 'queue.connections.database.connection'] as $connectionKey) {
            if (! config($connectionKey)) {
                config([$connectionKey => config('tenancy.central_connection')]);
            }
        }
        foreach (OrganizationScope::MODELS as $modelClass) {
            // Boot first so the scope is also present on route bindings and eager loads.
            $model = new $modelClass;
            $model::addGlobalScope(new OrganizationScope);
        }

        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class,
            ApplyOrganizationScope::class,
        );
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            ApplyOrganizationScope::class,
            EnterClientForSupport::class,
        );
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class,
            ApplyPlatformCompanyScope::class,
        );
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            ApplyPlatformCompanyScope::class,
            RequirePlatformAdmin::class,
        );
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            AuthenticatesRequests::class,
            ResolveClientSession::class,
        );
    }
}
