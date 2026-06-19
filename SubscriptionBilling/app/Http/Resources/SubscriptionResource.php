<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'client_id'  => $this->client_id,
            'plan_id'    => $this->plan_id,
            'status'     => $this->status,
            'started_at' => $this->started_at,
            'ends_at'    => $this->ends_at,
        ];
    }
}
