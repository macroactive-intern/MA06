<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'coach_id'      => $this->coach_id,
            'name'          => $this->name,
            'price_cents'   => $this->price_cents,
            'billing_cycle' => $this->billing_cycle,
            'active'        => $this->active,
        ];
    }
}
