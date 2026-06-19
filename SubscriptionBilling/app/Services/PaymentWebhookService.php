<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    /**
     * Process a payment webhook event idempotently.
     *
     * Returns ['payment' => Payment, 'created' => bool].
     * created = false means the event was already recorded; caller should return 200.
     * created = true means a new payment was inserted; caller should return 201.
     */
    public function process(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $existing = Payment::where('processor_event_id', $data['event_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('webhook.payment.duplicate', [
                    'event_id'   => $data['event_id'],
                    'payment_id' => $existing->id,
                ]);
                return ['payment' => $existing, 'created' => false];
            }

            try {
                $payment = Payment::create([
                    'subscription_id'    => $data['subscription_id'],
                    'amount_cents'       => $data['amount_cents'],
                    'status'             => $data['status'],
                    'processor_event_id' => $data['event_id'],
                    'processed_at'       => $data['processed_at'],
                ]);

                Log::info('webhook.payment.created', [
                    'event_id'        => $data['event_id'],
                    'payment_id'      => $payment->id,
                    'subscription_id' => $payment->subscription_id,
                ]);

                return ['payment' => $payment, 'created' => true];
            } catch (QueryException $e) {
                if ($this->isDuplicateKeyError($e)) {
                    $payment = Payment::where('processor_event_id', $data['event_id'])->firstOrFail();
                    return ['payment' => $payment, 'created' => false];
                }
                throw $e;
            }
        });
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || $e->getCode() === '23000';
    }
}
