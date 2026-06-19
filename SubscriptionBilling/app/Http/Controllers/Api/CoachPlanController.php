<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\MoneyService;
use Illuminate\Http\JsonResponse;

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

        return response()->json($plan, 201);
    }

    public function update(UpdateSubscriptionPlanRequest $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::where('id', $id)
            ->where('coach_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->only(['name', 'billing_cycle', 'active']);

        if ($request->has('price')) {
            $data['price_cents'] = MoneyService::toCents($request->price);
        }

        $plan->update($data);

        return response()->json($plan);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::where('id', $id)
            ->where('coach_id', auth()->id())
            ->firstOrFail();

        $plan->update(['active' => false]);

        return response()->json($plan);
    }
}
