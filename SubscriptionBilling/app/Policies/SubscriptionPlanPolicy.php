<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionPlanPolicy
{
    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->id === $plan->coach_id;
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $user->id === $plan->coach_id;
    }
}
