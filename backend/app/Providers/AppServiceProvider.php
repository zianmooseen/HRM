<?php

namespace App\Providers;

use App\Http\Middleware\ApplyOrganizationScope;
use App\Models\Scopes\OrganizationScope;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (OrganizationScope::MODELS as $modelClass) {
            // Boot first so the scope is also present on route bindings and eager loads.
            $model = new $modelClass;
            $model::addGlobalScope(new OrganizationScope);
        }

        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class,
            ApplyOrganizationScope::class,
        );
    }
}
