<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'plan_name'         => $this->plan->name,
            'status'            => $this->status,
            'started_at'        => $this->started_at,
            'next_billing_date' => $this->next_billing_date,
            'price_cents'       => $this->plan->price_cents,
        ];
    }
}
