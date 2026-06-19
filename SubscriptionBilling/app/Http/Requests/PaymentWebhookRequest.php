<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id'        => ['required', 'string', 'max:100'],
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'amount_cents'    => ['required', 'integer', 'min:0'],
            'status'          => ['required', 'in:succeeded,failed,refunded'],
            'processed_at'    => ['required', 'date'],
        ];
    }
}
