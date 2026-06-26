<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\ScanJob;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Target;
use App\Policies\OrganizationPolicy;
use App\Policies\ScanPolicy;
use App\Policies\TargetPolicy;


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
        Gate::policy(Target::class, ScanPolicy::class);
        Gate::policy(ScanJob::class, ScanPolicy::class);

        Gate::policy(Target::class, TargetPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);


        if ($this->app->environment('production')) 
        {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
