<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientSubscriptionRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BillingDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSubscriptionController extends Controller
{
    public function __construct(private BillingDateService $billingDateService) {}

    public function store(StoreClientSubscriptionRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        if (! $plan->active) {
            return response()->json(['message' => 'Plan is not active.'], 422);
        }

        $alreadySubscribed = Subscription::where('client_id', $request->user()->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->exists();

        if ($alreadySubscribed) {
            return response()->json(['message' => 'You already have an active subscription to this plan.'], 422);
        }

        $subscription = Subscription::create([
            'client_id'  => $request->user()->id,
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'started_at' => now(),
            'ends_at'    => null,
        ]);

        return response()->json($subscription->load('plan'), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::where('client_id', $request->user()->id)
            ->with('plan')
            ->get()
            ->map(function (Subscription $subscription) {
                return [
                    'id'                => $subscription->id,
                    'plan_name'         => $subscription->plan->name,
                    'status'            => $subscription->status,
                    'started_at'        => $subscription->started_at,
                    'next_billing_date' => $this->billingDateService
                        ->nextBillingDate($subscription)
                        ->toDateString(),
                    'price_cents'       => $subscription->plan->price_cents,
                ];
            });

        return response()->json(['data' => $subscriptions]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::where('id', $id)
            ->where('client_id', $request->user()->id)
            ->firstOrFail();

        $subscription->update([
            'status'  => 'cancelled',
            'ends_at' => $this->billingDateService->currentPeriodEnd($subscription),
        ]);

        return response()->json($subscription->fresh()->load('plan'));
    }
}
