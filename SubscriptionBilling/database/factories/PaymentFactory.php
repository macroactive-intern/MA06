<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subscription_id'    => Subscription::factory(),
            'amount_cents'       => fake()->numberBetween(999, 49999),
            'status'             => 'succeeded',
            'processor_event_id' => 'evt_' . fake()->unique()->lexify('????????'),
            'processed_at'       => now(),
        ];
    }
}
