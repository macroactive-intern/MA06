<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentWebhookService $webhookService) {}

    public function store(PaymentWebhookRequest $request): JsonResponse
    {
        $result = $this->webhookService->process($request->validated());

        $status = $result['created'] ? 201 : 200;

        return response()->json($result['payment'], $status);
    }
}
