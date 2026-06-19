<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

// -----------------------------------------------------------------------
// 1. Price conversion
// -----------------------------------------------------------------------

it('stores price as integer cents when a coach creates a plan', function () {
    $coach = User::factory()->create();

    $this->actingAs($coach)
        ->postJson('/api/coach/plans', [
            'name'          => 'Basic Coaching',
            'price'         => 49.99,
            'billing_cycle' => 'monthly',
        ])
        ->assertStatus(201);

    $this->assertDatabaseHas('subscription_plans', ['price_cents' => 4999]);
});

// -----------------------------------------------------------------------
// 2. Webhook creates payment
// -----------------------------------------------------------------------

it('records a new payment and returns 201 on first webhook delivery', function () {
    $subscription = Subscription::factory()->create();

    $this->postJson('/api/webhooks/payment', [
        'event_id'        => 'evt_abc123',
        'subscription_id' => $subscription->id,
        'amount_cents'    => 4999,
        'status'          => 'succeeded',
        'processed_at'    => '2026-06-15T09:00:00Z',
    ])->assertStatus(201);

    $this->assertDatabaseHas('payments', ['processor_event_id' => 'evt_abc123']);
});

// -----------------------------------------------------------------------
// 3. Webhook idempotency
// -----------------------------------------------------------------------

it('returns 200 and does not create a duplicate payment on repeated webhook delivery', function () {
    $subscription = Subscription::factory()->create();

    $payload = [
        'event_id'        => 'evt_abc123',
        'subscription_id' => $subscription->id,
        'amount_cents'    => 4999,
        'status'          => 'succeeded',
        'processed_at'    => '2026-06-15T09:00:00Z',
    ];

    $this->postJson('/api/webhooks/payment', $payload)->assertStatus(201);
    $this->postJson('/api/webhooks/payment', $payload)->assertStatus(200);

    $this->assertDatabaseCount('payments', 1);
});

// -----------------------------------------------------------------------
// 4. Monthly billing date — January 31
// -----------------------------------------------------------------------

it('returns next_billing_date of 2026-02-28 for a monthly subscription started on 2026-01-31', function () {
    Carbon::setTestNow('2026-01-31');

    $client = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
    Subscription::factory()->create([
        'client_id'  => $client->id,
        'plan_id'    => $plan->id,
        'started_at' => Carbon::parse('2026-01-31'),
    ]);

    $this->actingAs($client)
        ->getJson('/api/client/subscriptions')
        ->assertStatus(200)
        ->assertJsonPath('data.0.next_billing_date', '2026-02-28');

    Carbon::setTestNow();
});

// -----------------------------------------------------------------------
// 5. Quarterly billing date — March 15
// -----------------------------------------------------------------------

it('returns next_billing_date of 2026-06-15 for a quarterly subscription started on 2026-03-15', function () {
    Carbon::setTestNow('2026-03-15');

    $client = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['billing_cycle' => 'quarterly']);
    Subscription::factory()->create([
        'client_id'  => $client->id,
        'plan_id'    => $plan->id,
        'started_at' => Carbon::parse('2026-03-15'),
    ]);

    $this->actingAs($client)
        ->getJson('/api/client/subscriptions')
        ->assertStatus(200)
        ->assertJsonPath('data.0.next_billing_date', '2026-06-15');

    Carbon::setTestNow();
});

// -----------------------------------------------------------------------
// 6. Client ownership — subscription list
// -----------------------------------------------------------------------

it('returns only the authenticated clients own subscriptions', function () {
    $client1 = User::factory()->create();
    $client2 = User::factory()->create();
    $plan    = SubscriptionPlan::factory()->create();

    Subscription::factory()->create(['client_id' => $client1->id, 'plan_id' => $plan->id]);
    Subscription::factory()->create(['client_id' => $client2->id, 'plan_id' => $plan->id]);

    $this->actingAs($client1)
        ->getJson('/api/client/subscriptions')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// -----------------------------------------------------------------------
// 7. Client cannot cancel another client's subscription
// -----------------------------------------------------------------------

it('returns 404 when a client tries to cancel another clients subscription', function () {
    $client1 = User::factory()->create();
    $client2 = User::factory()->create();
    $plan    = SubscriptionPlan::factory()->create();

    $subscription = Subscription::factory()->create([
        'client_id' => $client1->id,
        'plan_id'   => $plan->id,
    ]);

    $this->actingAs($client2)
        ->postJson("/api/client/subscriptions/{$subscription->id}/cancel")
        ->assertStatus(404);
});

// -----------------------------------------------------------------------
// 8. Cancellation sets status and ends_at
// -----------------------------------------------------------------------

it('cancellation sets status to cancelled and ends_at to the current period end', function () {
    Carbon::setTestNow('2026-01-31');

    $client = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
    $subscription = Subscription::factory()->create([
        'client_id'  => $client->id,
        'plan_id'    => $plan->id,
        'started_at' => Carbon::parse('2026-01-31'),
        'status'     => 'active',
    ]);

    $this->actingAs($client)
        ->postJson("/api/client/subscriptions/{$subscription->id}/cancel")
        ->assertStatus(200);

    $subscription->refresh();
    expect($subscription->status)->toBe('cancelled');
    expect($subscription->ends_at->toDateString())->toBe('2026-02-28');

    Carbon::setTestNow();
});

// -----------------------------------------------------------------------
// 9. Coach plan ownership
// -----------------------------------------------------------------------

it('returns 404 when a coach tries to update another coachs plan', function () {
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['coach_id' => $coach1->id]);

    $this->actingAs($coach2)
        ->putJson("/api/coach/plans/{$plan->id}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when a coach tries to deactivate another coachs plan', function () {
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['coach_id' => $coach1->id]);

    $this->actingAs($coach2)
        ->deleteJson("/api/coach/plans/{$plan->id}")
        ->assertStatus(404);
});

// -----------------------------------------------------------------------
// 10. Coach subscription list ownership
// -----------------------------------------------------------------------

it('returns only active subscriptions for the authenticated coachs plans', function () {
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();
    $client = User::factory()->create();

    $plan1 = SubscriptionPlan::factory()->create(['coach_id' => $coach1->id]);
    $plan2 = SubscriptionPlan::factory()->create(['coach_id' => $coach2->id]);

    Subscription::factory()->create(['client_id' => $client->id, 'plan_id' => $plan1->id, 'status' => 'active']);
    Subscription::factory()->create(['client_id' => $client->id, 'plan_id' => $plan2->id, 'status' => 'active']);

    $this->actingAs($coach1)
        ->getJson('/api/coach/subscriptions')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// -----------------------------------------------------------------------
// 11. Inactive plan not in public listing
// -----------------------------------------------------------------------

it('does not return inactive plans in the public plans listing', function () {
    SubscriptionPlan::factory()->create(['active' => true]);
    SubscriptionPlan::factory()->create(['active' => false]);

    $this->getJson('/api/plans')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// -----------------------------------------------------------------------
// 12. Cannot subscribe to inactive plan
// -----------------------------------------------------------------------

it('returns 422 when a client tries to subscribe to an inactive plan', function () {
    $client = User::factory()->create();
    $plan   = SubscriptionPlan::factory()->create(['active' => false]);

    $this->actingAs($client)
        ->postJson('/api/client/subscriptions', ['plan_id' => $plan->id])
        ->assertStatus(422);
});
