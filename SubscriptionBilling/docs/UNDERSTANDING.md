What I need to build

This task is asking me to build a Laravel JSON API for subscription billing in MacroActive.

Coaches can create subscription plans for their clients. A plan has a name, a price, a billing cycle, and an active/inactive state. Clients can subscribe to a plan, list their own subscriptions, and cancel a subscription. Coaches can list active subscriptions across the plans they own.

The system also needs to accept payment webhook events from an external payment processor. These webhook events are not authenticated with Sanctum because they are sent by the payment processor, not by a logged-in user. The webhook must be idempotent because the payment processor may send the same event more than once.

Rules are:

    Money must be stored as integer cents, not decimals.
    A decimal price input like 49.99 must be converted to 4999 before storage.
    Payment webhook events must not create duplicates when the same event_id is received twice.
    Client subscription lists must only show the authenticated client's own subscriptions.
    next_billing_date must handle month-end dates correctly, especially January 31st to February 28th or 29th.
    Cancelling a subscription sets its status to cancelled and sets ends_at to the end of the current billing period.
    Proration for mid-cycle plan changes is out of scope.

--------------------------------------------------------------------------------------------------------------------------------------------------

What inputs it takes

        Plan endpoints

GET /api/plans

Returns all active subscription plans.

Expected response shape will probably be something like:

```json
{
  "data": [
    {
      "id": 1,
      "coach_id": 5,
      "name": "Basic Coaching",
      "price_cents": 4999,
      "billing_cycle": "monthly",
      "active": true
    }
  ]
}
```

This endpoint lists active plans only.

----------------------------------------------------------

POST /api/coach/plans

Authenticated coach creates a subscription plan.

Expected input:

```json
{
  "name": "Basic Coaching",
  "price": 49.99,
  "billing_cycle": "monthly"
}
```

The database column is price_cents, but the API accepts a decimal price like 49.99.

So the API input needs to accept a decimal price, convert it to cents, and store it in price_cents.

Example:

Input price: 49.99
Stored price_cents: 4999

----------------------------------------------------------

PUT /api/coach/plans/{id}

Authenticated coach updates one of their own plans.

Possible input:

```json
{
  "name": "Elite Coaching",
  "price": 149.99,
  "billing_cycle": "monthly",
  "active": true
}
```

The coach should only be able to update plans where coach_id is the authenticated user's ID.

----------------------------------------------------------

DELETE /api/coach/plans/{id}

Authenticated coach deactivates one of their own plans.

This should not hard delete the row. It should set:

active = false

This means existing subscription data and payment history remain intact.

----------------------------------------------------------

        Subscription endpoints

POST /api/client/subscriptions

Authenticated client subscribes to a plan.

Expected input:

```json
{
  "plan_id": 1
}
```

The API should create a subscription with:

client_id = authenticated user ID
plan_id = selected plan ID
status = active
started_at = current timestamp
ends_at = null

----------------------------------------------------------

GET /api/client/subscriptions

Authenticated client lists their own subscriptions.

Expected response from the brief:

```json
{
  "data": [
    {
      "id": 1,
      "plan_name": "Basic Coaching",
      "status": "active",
      "started_at": "2026-01-31T00:00:00.000000Z",
      "next_billing_date": "2026-02-28",
      "price_cents": 4999
    }
  ]
}
```

This endpoint must only return subscriptions where:

subscriptions.client_id = authenticated user ID

That is what prevents one client from seeing another client's subscriptions.

The response also needs to include a computed next_billing_date.

----------------------------------------------------------

POST /api/client/subscriptions/{id}/cancel

Authenticated client cancels one of their own subscriptions.

Expected behavior:

status = cancelled
ends_at = end of current billing period

The client should only be allowed to cancel subscriptions where:

client_id = authenticated user ID

The exact calculation for ends_at depends on the subscription's billing cycle and start date. For example, a monthly subscription started on January 31st should have a billing period ending at the February renewal date.

----------------------------------------------------------

GET /api/coach/subscriptions

Authenticated coach lists active subscriptions across their own plans.

This should return active subscriptions where the subscription's plan belongs to the authenticated coach:

subscriptions.status = active
subscription_plans.coach_id = authenticated user ID

The coach should not see subscriptions for plans owned by other coaches.

----------------------------------------------------------

        Webhook endpoint

POST /api/webhooks/payment

This endpoint has no auth:sanctum middleware.

Expected input:

```json
{
  "event_id": "evt_abc123",
  "subscription_id": 42,
  "amount_cents": 4999,
  "status": "succeeded",
  "processed_at": "2026-06-15T09:00:00Z"
}
```

Expected behavior:

First time receiving event_id = evt_abc123:
Create a payment record.
Store processor_event_id = evt_abc123.
Return HTTP 201.
Second time receiving the same event_id = evt_abc123:
Do not create another payment record.
Return HTTP 200.
The unique database constraint on payments.processor_event_id backs up the idempotency rule.

--------------------------------------------------------------------------------------------------------------------------------------------------

Next billing date calculation

Subscriptions renew on the same day of the month they started.

The tricky rule is that if the original start day does not exist in the renewal month, the renewal falls on the last day of that month.

This means I cannot use date math that accidentally overflows into the next month.

For example, adding one month to January 31st must produce February 28th in 2026, not March 2nd.

--------------------------------------------------------------------------------------------------------------------------------------------------

What it returns

This is a JSON API, so it does not display pages.

It returns JSON responses for:

Active subscription plans
Created coach plans
Updated coach plans
Deactivated plans
Created client subscriptions
Client subscriptions with computed next_billing_date
Cancelled subscriptions
Coach subscription lists
Webhook payment processing results

Expected important response codes:

GET /api/plans                         200
POST /api/coach/plans                  201
PUT /api/coach/plans/{id}              200
DELETE /api/coach/plans/{id}           200 or 204
POST /api/client/subscriptions         201
GET /api/client/subscriptions          200
POST /api/client/subscriptions/{id}/cancel 200
GET /api/coach/subscriptions           200
POST /api/webhooks/payment             201 for new event, 200 for duplicate event

--------------------------------------------------------------------------------------------------------------------------------------------------

How i will distinguish coaches from clients?

There is a role column on users so will enforce ownership through coach_id and client_id. I will not build a full role/permission system.

--------------------------------------------------------------------------------------------------------------------------------------------------

What input field should be used for plan price?

The API should accept price as a decimal input and store price_cents internally. I will not allow clients to directly set price_cents

--------------------------------------------------------------------------------------------------------------------------------------------------

How precise price validation should be?

Prices should be validated as numeric with a minimum greater than or equal to zero and up to two decimal places.

--------------------------------------------------------------------------------------------------------------------------------------------------

Whether clients can have multiple active subscriptions

I will allow multiple subscriptions but I will add validation to prevent duplicate active subscriptions to the same plan

--------------------------------------------------------------------------------------------------------------------------------------------------

What happens when a plan is deactivated

Deactivating a plan prevents new subscriptions to that plan but does not cancel existing subscriptions.

--------------------------------------------------------------------------------------------------------------------------------------------------

What happens when a coach updates a plan price

Existing subscriptions will show the current plan price because there is no separate locked-in subscription price column

--------------------------------------------------------------------------------------------------------------------------------------------------

How to calculate next_billing_date after many billing cycles

I would calculate the next billing date after the current date or after the latest billing period. For the task tests, I will make sure the month-end logic gives the exact expected value.

--------------------------------------------------------------------------------------------------------------------------------------------------

How cancellation period end is calculated

ends_at should be set to the upcoming billing date for the current period, calculated using the same non-overflow billing date algorithm

--------------------------------------------------------------------------------------------------------------------------------------------------

What payment statuses should do to subscription status

The webhook records the payment. I will not automatically mutate subscription status from payment status unless required by tests.

--------------------------------------------------------------------------------------------------------------------------------------------------

Whether webhook amount must match the plan price

validate that amount_cents is an unsigned integer and record what the processor sent, make sure it matches the plan price

--------------------------------------------------------------------------------------------------------------------------------------------------

Webhook authentication and production protection

In production, this endpoint should still be protected by processor-specific verification, such as:

Signed webhook payloads
HMAC signature verification
Timestamp tolerance to prevent replay attacks
Secret webhook signing keys
HTTPS only
Possibly IP allowlists, depending on the processor

For this task, I will leave the endpoint unauthenticated by Sanctum but clearly note that production should verify webhook signatures.

--------------------------------------------------------------------------------------------------------------------------------------------------

Proration is out of scope

So do not implement:

Mid-cycle upgrades
Mid-cycle downgrades
Partial refunds
Partial charges
Credit balances