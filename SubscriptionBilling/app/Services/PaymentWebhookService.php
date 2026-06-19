<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\QueryException;

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
        $existing = Payment::where('processor_event_id', $data['event_id'])->first();

        if ($existing) {
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

            return ['payment' => $payment, 'created' => true];
        } catch (QueryException $e) {
            // Race condition: duplicate delivery arrived between the check and the insert
            if ($this->isDuplicateKeyError($e)) {
                $payment = Payment::where('processor_event_id', $data['event_id'])->firstOrFail();
                return ['payment' => $payment, 'created' => false];
            }

            throw $e;
        }
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        // SQLite: UNIQUE constraint failed  |  MySQL: Duplicate entry (code 23000)
        return str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || $e->getCode() === '23000';
    }
}
