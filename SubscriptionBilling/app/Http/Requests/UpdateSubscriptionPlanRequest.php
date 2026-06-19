<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
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
        $cycles = implode(',', array_keys(config('billing.cycles')));

        return [
            'name'          => ['sometimes', 'string', 'max:100'],
            'price'         => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'billing_cycle' => ['sometimes', "in:{$cycles}"],
            'active'        => ['sometimes', 'boolean'],
        ];
    }
}
