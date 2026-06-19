<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\MoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoachPlanController extends Controller
{
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::create([
            'coach_id'      => $request->user()->id,
            'name'          => $request->name,
            'price_cents'   => MoneyService::toCents($request->price),
            'billing_cycle' => $request->billing_cycle,
            'active'        => true,
        ]);

        Log::info('plan.created', [
            'plan_id'  => $plan->id,
            'coach_id' => $plan->coach_id,
        ]);

        return response()->json(new SubscriptionPlanResource($plan), 201);
    }

    public function update(UpdateSubscriptionPlanRequest $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $data = $request->only(['name', 'billing_cycle', 'active']);
        if ($request->has('price')) {
            $data['price_cents'] = MoneyService::toCents($request->price);
        }

        DB::transaction(function () use ($plan, $data): void {
            $plan->lockForUpdate();
            $plan->update($data);
        });

        Log::info('plan.updated', [
            'plan_id'  => $plan->id,
            'coach_id' => $request->user()->id,
        ]);

        return response()->json(new SubscriptionPlanResource($plan->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->authorize('delete', $plan);

        DB::transaction(function () use ($plan): void {
            $plan->lockForUpdate();
            $plan->update(['active' => false]);
        });

        Log::info('plan.deactivated', [
            'plan_id'  => $plan->id,
            'coach_id' => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }
}
