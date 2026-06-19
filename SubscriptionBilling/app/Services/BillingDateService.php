<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class BillingDateService
{
    /**
     * Next billing date strictly after $asOf, anchored to the original start day.
     * Month-end dates are capped to the last day of the target month (no overflow).
     */
    public function nextBillingDate(Subscription $subscription, ?Carbon $asOf = null): Carbon
    {
        $asOf      ??= Carbon::now();
        $start       = $subscription->started_at;
        $anchorDay   = (int) $start->format('j');
        $cycles      = config('billing.cycles');
        $months      = (int) $cycles[$subscription->plan->billing_cycle];

        $n = 1;
        while (true) {
            $candidate = $start->copy()->addMonthsNoOverflow($n * $months);
            $candidate->setDay(min($anchorDay, $candidate->daysInMonth));

            if ($candidate->greaterThan($asOf)) {
                return $candidate;
            }
            $n++;
        }
    }

    /**
     * End of the current billing period — used to set ends_at on cancellation.
     */
    public function currentPeriodEnd(Subscription $subscription, ?Carbon $asOf = null): Carbon
    {
        return $this->nextBillingDate($subscription, $asOf);
    }
}
