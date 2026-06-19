<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientSubscriptionRequest;
use App\Http\Resources\ClientSubscriptionResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BillingDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClientSubscriptionController extends Controller
{
    public function __construct(private BillingDateService $billingDateService) {}

    public function store(StoreClientSubscriptionRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        if (! $plan->active) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not active.'],
            ]);
        }

        $subscription = DB::transaction(function () use ($request, $plan) {
            $alreadySubscribed = Subscription::where('client_id', $request->user()->id)
                ->where('plan_id', $plan->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

            if ($alreadySubscribed) {
                throw ValidationException::withMessages([
                    'plan_id' => ['You already have an active subscription to this plan.'],
                ]);
            }

            return Subscription::create([
                'client_id'  => $request->user()->id,
                'plan_id'    => $plan->id,
                'status'     => 'active',
                'started_at' => now(),
                'ends_at'    => null,
            ]);
        });

        Log::info('subscription.created', [
            'subscription_id' => $subscription->id,
            'client_id'       => $subscription->client_id,
            'plan_id'         => $subscription->plan_id,
        ]);

        return response()->json(new SubscriptionResource($subscription), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::where('client_id', $request->user()->id)
            ->with('plan')
            ->get()
            ->map(function (Subscription $subscription) {
                $subscription->next_billing_date = $this->billingDateService
                    ->nextBillingDate($subscription)
                    ->toDateString();
                return new ClientSubscriptionResource($subscription);
            });

        return response()->json(['data' => $subscriptions]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $this->authorize('cancel', $subscription);

        DB::transaction(function () use ($subscription): void {
            $subscription->lockForUpdate();
            $subscription->update([
                'status'  => 'cancelled',
                'ends_at' => $this->billingDateService->currentPeriodEnd($subscription),
            ]);
        });

        Log::info('subscription.cancelled', [
            'subscription_id' => $subscription->id,
            'client_id'       => $subscription->client_id,
        ]);

        return response()->json(new SubscriptionResource($subscription->fresh()));
    }
}
