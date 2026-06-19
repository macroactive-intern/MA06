<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id'  => User::factory(),
            'plan_id'    => SubscriptionPlan::factory(),
            'status'     => 'active',
            'started_at' => now(),
            'ends_at'    => null,
        ];
    }
}
