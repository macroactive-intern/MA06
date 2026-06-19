<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
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
            'name'          => ['required', 'string', 'max:100'],
            'price'         => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,annual'],
        ];
    }
}
