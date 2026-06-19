<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereHas('plan', function ($query) use ($request): void {
                $query->where('coach_id', $request->user()->id);
            })
            ->with(['plan', 'client'])
            ->get();

        return response()->json(['data' => SubscriptionResource::collection($subscriptions)]);
    }
}
