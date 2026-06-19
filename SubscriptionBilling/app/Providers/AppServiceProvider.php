<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Policies\SubscriptionPlanPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(SubscriptionPlan::class, SubscriptionPlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
    }
}
