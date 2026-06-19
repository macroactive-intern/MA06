<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'subscription_id'    => $this->subscription_id,
            'amount_cents'       => $this->amount_cents,
            'status'             => $this->status,
            'processor_event_id' => $this->processor_event_id,
            'processed_at'       => $this->processed_at,
        ];
    }
}
