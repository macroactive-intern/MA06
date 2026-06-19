<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'coach_id'      => User::factory(),
            'name'          => fake()->words(3, true),
            'price_cents'   => fake()->numberBetween(999, 49999),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
            'active'        => true,
        ];
    }
}
