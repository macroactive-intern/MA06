Goal

I am building a Laravel JSON API for subscription billing.

Coaches can create and manage subscription plans. Clients can subscribe to active plans, view their own subscriptions, and cancel them. Coaches can view active subscriptions attached to their own plans. The API also receives unauthenticated payment webhook events from a payment processor and records payments without creating duplicates when the same event is delivered more than once.

The main technical rules are:

Store money as integer cents.
Accept decimal price input like 49.99 and store it as 4999.
Calculate billing dates without month overflow.
Make payment webhooks idempotent.
Scope client data by client_id.
Scope coach data by coach_id.
Leave the webhook route outside auth:sanctum.
Explicitly keep proration out of scope.
Project setup

I will start from an empty Laravel project:

composer create-project laravel/laravel subscription-billing
cd subscription-billing
php artisan install:api

I will configure SQLite in .env for local development and testing.

Expected .env setup:

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

I will create the SQLite file:

touch database/database.sqlite

Then I will run the default migrations:

php artisan migrate
Libraries and packages
Laravel

Laravel will provide the routing, controllers, validation, Eloquent models, database migrations, factories, and feature testing.

Laravel Sanctum

Sanctum is needed because the brief says coach and client endpoints use auth:sanctum.

Routes protected by Sanctum:

POST   /api/coach/plans
PUT    /api/coach/plans/{id}
DELETE /api/coach/plans/{id}
POST   /api/client/subscriptions
GET    /api/client/subscriptions
POST   /api/client/subscriptions/{id}/cancel
GET    /api/coach/subscriptions

The webhook route will not use Sanctum.

SQLite

SQLite will be used for local development and tests because the brief asks for it.

Eloquent

Eloquent will be used for:

Model relationships
Ownership queries
Casting timestamps and booleans
Query scoping by authenticated user
Form Requests

Form request classes will keep validation out of controllers.

I will create requests for:

Creating coach plans
Updating coach plans
Creating client subscriptions
Processing payment webhooks
Carbon

Carbon will be used for billing date calculations.

Important note: I will avoid month overflow. A monthly subscription started on January 31st must renew on February 28th in 2026, not March 2nd.

Pest or Laravel feature tests

I will use Laravel feature tests to prove the acceptance criteria. If the project is set up with Pest, I will write Pest tests. Otherwise, I will use PHPUnit-style Laravel tests.

Data model

There are three required tables:

subscription_plans
subscriptions
payments

I will also use the existing Laravel users table for coaches and clients.

The brief does not fully define a role system. My main security rule will be ownership-based access:

Coach-owned records are filtered by coach_id.
Client-owned records are filtered by client_id.

If a simple role column already exists on users, I can use it in tests. I will not build a full permission system because it is not part of the brief.

Table: subscription_plans

This table stores plans created by coaches.

Columns:

id              bigIncrements
coach_id        foreignId references users.id
name            string(100)
price_cents     unsignedInteger
billing_cycle   enum: monthly, quarterly, annual
active          boolean default true
created_at      timestamp nullable
updated_at      timestamp nullable

Constraints:

coach_id references users.id.
name is limited to 100 characters.
price_cents stores integer cents only.
billing_cycle must be one of:
monthly
quarterly
annual
active defaults to true.

Relationship plan:

SubscriptionPlan belongsTo User as coach
SubscriptionPlan hasMany Subscription

I will not hard delete plans. The delete endpoint will set:

active = false

This preserves existing subscriptions and payment history.

Table: subscriptions

This table stores client subscriptions to plans.

Columns:

id              bigIncrements
client_id       foreignId references users.id
plan_id         foreignId references subscription_plans.id
status          enum: active, cancelled, past_due
started_at      timestamp
ends_at         timestamp nullable
created_at      timestamp nullable
updated_at      timestamp nullable

Constraints:

client_id references users.id.
plan_id references subscription_plans.id.
status must be one of:
active
cancelled
past_due
ends_at is nullable because active subscriptions do not have an end date yet.

Relationship plan:

Subscription belongsTo User as client
Subscription belongsTo SubscriptionPlan as plan
Subscription hasMany Payment

I will allow a client to have multiple subscriptions, but I will prevent duplicate active subscriptions to the exact same plan.

That means a client should not be able to create two active subscriptions for the same plan_id.

Table: payments

This table stores payment processor events.

Columns:

id                    bigIncrements
subscription_id        foreignId references subscriptions.id
amount_cents           unsignedInteger
status                 enum: succeeded, failed, refunded
processor_event_id     string(100) unique
processed_at           timestamp
created_at             timestamp nullable
updated_at             timestamp nullable

Constraints:

subscription_id references subscriptions.id.
amount_cents stores integer cents only.
status must be one of:
succeeded
failed
refunded
processor_event_id is unique.
processor_event_id is limited to 100 characters.

Relationship plan:

Payment belongsTo Subscription

The unique constraint on processor_event_id is required for webhook idempotency.

Even if the controller checks for an existing event first, the database constraint is still needed to protect against duplicate rows.

Money storage decision

Money will never be stored as decimal dollars in the database.

The plans table stores:

price_cents

The payments table stores:

amount_cents

Example:

Input price: 49.99
Stored value: 4999

Example:

Input price: 149.99
Stored value: 14999

The API will accept a decimal price field when a coach creates or updates a plan.

Example request:

{
  "name": "Basic Coaching",
  "price": 49.99,
  "billing_cycle": "monthly"
}

The controller or service will convert it before saving:

price = 49.99
price_cents = 4999

I will not allow users to directly submit price_cents when creating or updating a plan. The API input is the user-friendly decimal price. The database stores the safe integer value.

For validation, prices should have no more than two decimal places. Values like 49.99 are valid. Values like 49.999 should not be accepted because the API should not silently round money in a surprising way.

Money conversion approach

I will create a small service/helper for converting price input to cents.

Possible file:

app/Services/MoneyService.php

The service will handle:

49.99  -> 4999
149.99 -> 14999
10     -> 1000
0      -> 0

I will avoid relying on raw floating point math as much as possible. The safer approach is to normalize the submitted value as a string, validate it has at most two decimal places, then convert dollars and cents into an integer.

Example logic:

"49.99"
dollars = 49
cents = 99
price_cents = 49 * 100 + 99
price_cents = 4999
Billing cycle rules

Supported billing cycles:

monthly   = 1 month
quarterly = 3 months
annual    = 12 months

The renewal day is based on the original started_at day.

Examples:

Started: 2026-03-15
Cycle: quarterly

Next billing date: 2026-06-15
Following billing date: 2026-09-15

For month-end dates, if the target month does not have the original day, the billing date becomes the last day of that target month.

Example:

Started: 2026-01-31
Cycle: monthly

February 2026 does not have a 31st.
So next billing date is 2026-02-28.

This exact value is required by the acceptance criteria:

{
  "next_billing_date": "2026-02-28"
}
Next billing date algorithm

I will create a service for billing date calculations.

Possible file:

app/Services/BillingDateService.php

The service will provide methods like:

nextBillingDate(subscription)
currentPeriodEnd(subscription)

The algorithm will:

Read the subscription started_at.
Read the related plan billing_cycle.
Convert the billing cycle into month increments:
monthly = 1
quarterly = 3
annual = 12
Use the original start day as the anchor day.
Move forward by the correct number of months.
Check the number of days in the target month.
If the anchor day exists in the target month, use it.
If the anchor day does not exist, use the last day of the target month.
Return the date in Y-m-d format for the API response.

Important edge case:

2026-01-31 + 1 month should be 2026-02-28

It must not become:

2026-03-02

I will use Carbon carefully and avoid overflow behavior.

Cancellation approach

The cancel endpoint is:

POST /api/client/subscriptions/{id}/cancel

When a client cancels a subscription, I will:

Require auth:sanctum.
Find the subscription where:
id equals the route parameter.
client_id equals the authenticated user's ID.
If not found, return 404.
Calculate the end of the current billing period using the same billing date service.
Update the subscription:
status = cancelled
ends_at = current period end

I will not hard delete the subscription.

If the subscription is already cancelled, I will keep the operation safe and not create inconsistent data. The endpoint can either return the existing cancelled subscription or leave the values unchanged.

Webhook idempotency approach

The webhook endpoint is:

POST /api/webhooks/payment

This endpoint receives payment events from an external payment processor.

Example request:

{
  "event_id": "evt_abc123",
  "subscription_id": 42,
  "amount_cents": 4999,
  "status": "succeeded",
  "processed_at": "2026-06-15T09:00:00Z"
}

The request field is named:

event_id

The database column is named:

processor_event_id

So I will map:

event_id -> processor_event_id

First delivery behavior:

event_id = evt_abc123
payment does not exist yet
create payment
return 201

Duplicate delivery behavior:

event_id = evt_abc123
payment already exists
do not create another payment
return 200

The database will enforce:

unique(processor_event_id)

That unique constraint is important because payment processors may retry webhook delivery, and two duplicate requests could theoretically arrive close together.

Controller-level check:

Find payment by processor_event_id.
If it exists, return 200.
If it does not exist, create it and return 201.

Database-level protection:

payments.processor_event_id unique

If a duplicate insert happens because of a race condition, I will catch the duplicate-key error and return 200 instead of creating a second row.

Webhook auth approach

The webhook route will not use auth:sanctum.

That is intentional because the webhook is called by a payment processor, not by a logged-in MacroActive user.

Route:

POST /api/webhooks/payment

This route will be outside the Sanctum middleware group.

In production, this endpoint should still be protected. It should use processor-specific webhook security, such as:

HMAC signature verification
Signed webhook payloads
Webhook secret keys
Timestamp tolerance to prevent replay attacks
HTTPS only
Possibly IP allowlisting if the processor supports it

For this task, I will document the production security requirement but not implement real payment processor signature verification because no processor or signing secret is specified in the brief.

Payment status approach

Payments can have these statuses:

succeeded
failed
refunded

Subscriptions can have these statuses:

active
cancelled
past_due

The brief does not say that a failed payment should automatically change a subscription to past_due, or that a refunded payment should cancel a subscription.

So the webhook will record the payment event, but it will not automatically mutate subscription status unless the task tests require it.

This keeps the webhook behavior focused on the stated acceptance criteria:

record payment
return 201
do not duplicate payment for repeated event_id
Webhook amount validation

The webhook sends:

amount_cents

This must be validated as an integer greater than or equal to zero.

The amount should usually match the subscription plan price, but real payment systems can have discounts, tax, credits, or adjustments. Since the brief does not define discounts or taxes, I will keep the task simple.

For this project:

I will validate amount_cents as an unsigned integer.
I will store the exact amount sent by the processor.
I will not implement discounts, taxes, credits, or proration.
I can compare the amount against the current plan price in tests or logs, but I will not build a full billing reconciliation system.
Proration is out of scope

Proration for mid-cycle plan changes is explicitly out of scope.

I will not implement:

Mid-cycle upgrades
Mid-cycle downgrades
Partial refunds
Partial charges
Credit balances
Subscription plan swapping
Billing adjustments for plan changes

If plan changes are added later, they should be designed separately with explicit proration rules.

Routes and controllers

I will organize the routes in routes/api.php.

Public routes

These routes do not require Sanctum:

Route::get('/plans', [SubscriptionPlanController::class, 'index']);
Route::post('/webhooks/payment', [PaymentWebhookController::class, 'store']);
Authenticated routes

These routes require Sanctum:

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coach/plans', [CoachPlanController::class, 'store']);
    Route::put('/coach/plans/{plan}', [CoachPlanController::class, 'update']);
    Route::delete('/coach/plans/{plan}', [CoachPlanController::class, 'destroy']);

    Route::post('/client/subscriptions', [ClientSubscriptionController::class, 'store']);
    Route::get('/client/subscriptions', [ClientSubscriptionController::class, 'index']);
    Route::post('/client/subscriptions/{subscription}/cancel', [ClientSubscriptionController::class, 'cancel']);

    Route::get('/coach/subscriptions', [CoachSubscriptionController::class, 'index']);
});
Controller structure

I will create these controllers:

app/Http/Controllers/Api/SubscriptionPlanController.php
app/Http/Controllers/Api/CoachPlanController.php
app/Http/Controllers/Api/ClientSubscriptionController.php
app/Http/Controllers/Api/CoachSubscriptionController.php
app/Http/Controllers/Api/PaymentWebhookController.php
SubscriptionPlanController

Handles public plan listing.

index

Endpoint:

GET /api/plans

Behavior:

Query active plans only.
Return JSON response.
Do not return inactive plans.

Query:

SubscriptionPlan::query()
    ->where('active', true)
    ->get();
CoachPlanController

Handles coach-owned plan management.

store

Endpoint:

POST /api/coach/plans

Behavior:

Require authenticated user.
Validate input.
Accept decimal price.
Convert price to price_cents.
Create plan with coach_id = auth()->id().
Set active = true by default.
Return 201.
update

Endpoint:

PUT /api/coach/plans/{id}

Behavior:

Require authenticated user.
Find plan by ID and coach_id = auth()->id().
If the plan belongs to another coach, return 404.
Validate input.
If price is present, convert it to price_cents.
Update only allowed fields.
Return 200.

Allowed update fields:

name
price
billing_cycle
active

The request field is price, but the stored field is price_cents.

destroy

Endpoint:

DELETE /api/coach/plans/{id}

Behavior:

Require authenticated user.
Find plan by ID and coach_id = auth()->id().
If the plan belongs to another coach, return 404.
Set active = false.
Return 200 or 204.

I will use soft deactivation instead of hard delete because the brief says delete means deactivate.

ClientSubscriptionController

Handles client subscription actions.

store

Endpoint:

POST /api/client/subscriptions

Behavior:

Require authenticated user.
Validate plan_id.
Ensure the plan exists.
Ensure the plan is active.
Prevent duplicate active subscription to the same plan for the same client.
Create subscription:
client_id = auth()->id()
plan_id = request plan_id
status = active
started_at = now()
ends_at = null
Return 201.
index

Endpoint:

GET /api/client/subscriptions

Behavior:

Require authenticated user.
Query subscriptions where:
client_id = auth()->id()
Eager load the related plan.
Compute next_billing_date for each subscription.
Return only the authenticated client's subscriptions.

Response shape:

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

Ownership rule:

A client cannot see another client's subscriptions because the query filters by client_id = auth()->id().
cancel

Endpoint:

POST /api/client/subscriptions/{id}/cancel

Behavior:

Require authenticated user.
Find subscription where:
id = route id
client_id = auth()->id()
If not found, return 404.
Calculate current period end.
Set:
status = cancelled
ends_at = calculated period end
Return 200.
CoachSubscriptionController

Handles coach view of active subscriptions.

index

Endpoint:

GET /api/coach/subscriptions

Behavior:

Require authenticated user.
Query active subscriptions where the related plan belongs to the authenticated coach.
Eager load plan and client.
Return active subscriptions only.

Query idea:

Subscription::query()
    ->where('status', 'active')
    ->whereHas('plan', function ($query) {
        $query->where('coach_id', auth()->id());
    })
    ->with(['plan', 'client'])
    ->get();

Ownership rule:

A coach cannot see subscriptions for another coach's plans because the query filters through subscription_plans.coach_id = auth()->id().
PaymentWebhookController

Handles payment processor events.

store

Endpoint:

POST /api/webhooks/payment

Behavior:

No Sanctum auth.
Validate request.
Check for existing payment by processor_event_id.
If payment exists, return 200.
If payment does not exist, create it and return 201.
Keep unique database index on processor_event_id.

New event response:

201 Created

Duplicate event response:

200 OK
Form requests

I will create these request classes:

app/Http/Requests/StoreSubscriptionPlanRequest.php
app/Http/Requests/UpdateSubscriptionPlanRequest.php
app/Http/Requests/StoreClientSubscriptionRequest.php
app/Http/Requests/PaymentWebhookRequest.php
StoreSubscriptionPlanRequest

Rules:

name required string max:100
price required numeric min:0 decimal with max 2 places
billing_cycle required in:monthly,quarterly,annual
UpdateSubscriptionPlanRequest

Rules:

name sometimes string max:100
price sometimes numeric min:0 decimal with max 2 places
billing_cycle sometimes in:monthly,quarterly,annual
active sometimes boolean
StoreClientSubscriptionRequest

Rules:

plan_id required exists:subscription_plans,id

Extra rule:

The selected plan must be active.
The client must not already have an active subscription to the same plan.
PaymentWebhookRequest

Rules:

event_id required string max:100
subscription_id required exists:subscriptions,id
amount_cents required integer min:0
status required in:succeeded,failed,refunded
processed_at required date
API response decisions

I will wrap list responses in a data key.

Example:

{
  "data": []
}

For create/update/cancel responses, I will return the affected resource or a success message with the resource.

Important status codes:

GET /api/plans                              200
POST /api/coach/plans                       201
PUT /api/coach/plans/{id}                   200
DELETE /api/coach/plans/{id}                200 or 204
POST /api/client/subscriptions              201
GET /api/client/subscriptions               200
POST /api/client/subscriptions/{id}/cancel  200
GET /api/coach/subscriptions                200
POST /api/webhooks/payment                  201 for new event
POST /api/webhooks/payment                  200 for duplicate event

For ownership failures, I will prefer 404 so the API does not reveal that another user's record exists.

Main edge cases
Decimal money input

A coach sends:

{
  "price": 49.99
}

The database must store:

price_cents = 4999

I need to avoid storing 49.99 directly in the database.

Price precision

A price with more than two decimal places should not be accepted.

Bad example:

49.999

Reason:

The system should not silently round money input.
Duplicate payment webhook

The payment processor may send the same event more than once.

First request:

event_id = evt_abc123
create payment
return 201

Second request:

event_id = evt_abc123
do not create payment
return 200

The payments.processor_event_id unique index backs this up.

Month-end billing dates

A monthly subscription started on:

2026-01-31

must have:

next_billing_date = 2026-02-28

It must not become:

2026-03-02

This is one of the most important edge cases in the task.

Leap year February

If a subscription starts on January 31st and the next billing month is February in a leap year, the next billing date should be February 29th.

Example:

2028-01-31 monthly -> 2028-02-29
Quarterly billing

A quarterly subscription started on:

2026-03-15

should renew on:

2026-06-15

The next one after that is:

2026-09-15
Client ownership

Client subscription list must only return:

subscriptions.client_id = auth()->id()

A client must not see another client's subscriptions.

Client cancel ownership

A client can only cancel their own subscriptions.

The query must include:

id = route id
client_id = auth()->id()
Coach plan ownership

A coach can only update or deactivate plans where:

coach_id = auth()->id()

A coach must not update another coach's plan.

Coach subscription ownership

A coach can only see active subscriptions attached to plans where:

subscription_plans.coach_id = auth()->id()
Inactive plans

Inactive plans should not appear in:

GET /api/plans

Clients should not be able to create new subscriptions to inactive plans.

Existing subscriptions on inactive plans are not automatically cancelled.

Already cancelled subscriptions

If a subscription is already cancelled, the cancel endpoint should not create bad data.

I will keep the endpoint idempotent enough that repeating the cancel request does not break the subscription.

Missing webhook subscription

If the webhook references a subscription_id that does not exist, validation should fail.

Invalid webhook status

The webhook status must be one of:

succeeded
failed
refunded

Any other status should fail validation.

Decisions for unclear parts
Coach/client roles

The brief says coach endpoints and client endpoints use Sanctum, but does not define a full role system.

Decision:

I will not build a complex role system. I will rely on ownership checks using coach_id and client_id. If a simple user role field is already available, I can use it, but ownership checks are the main security requirement.

Plan price input field

The database stores price_cents, but the API accepts decimal price input.

Decision:

The request field will be:

price

The stored field will be:

price_cents

I will not accept direct user input for price_cents on coach plan create/update.

Existing subscriptions after plan deactivation

The brief says deleting a plan means setting active = false.

Decision:

Deactivating a plan prevents new subscriptions, but existing subscriptions remain unchanged.

Existing subscriptions after plan price update

The data model does not include a subscription-level locked price.

Decision:

Existing subscriptions will show the current plan price because price_cents lives on the plan, not the subscription.

A production billing system might snapshot the price onto the subscription, but that is outside this brief.

Multiple active subscriptions

The brief does not say whether a client can subscribe to the same plan more than once.

Decision:

A client can have multiple subscriptions, but not multiple active subscriptions to the same plan.

Payment statuses changing subscriptions

The brief does not specify whether payment status should update subscription status.

Decision:

The webhook records payments only. It will not automatically set subscriptions to past_due, active, or cancelled.

Proration

Proration is explicitly out of scope.

Decision:

I will not implement mid-cycle plan changes or prorated billing.

Test approach

I will create feature tests for the acceptance criteria.

Required tests:

A coach can create a plan with decimal price 49.99.
The database stores price_cents = 4999.
A webhook payment event creates a payment and returns 201.
Sending the same webhook event_id again returns 200.
Duplicate webhook delivery does not create a second payment row.
A monthly subscription started on 2026-01-31 returns next_billing_date = 2026-02-28.
A quarterly subscription started on 2026-03-15 returns next_billing_date = 2026-06-15.
Client subscription list only returns the authenticated client's subscriptions.
A client cannot cancel another client's subscription.
Cancelling a subscription sets status = cancelled.
Cancelling a subscription sets ends_at to the current period end.
A coach cannot update another coach's plan.
A coach cannot deactivate another coach's plan.
Coach subscription list only returns active subscriptions across that coach's plans.
Inactive plans do not appear in GET /api/plans.
Clients cannot subscribe to inactive plans.
Final build order

I will build the project in this order:

Create Laravel project.
Configure SQLite.
Install and confirm API/Sanctum setup.
Write migrations.
Create models and relationships.
Add form request validation.
Add money conversion service.
Add billing date service.
Add controllers.
Add routes.
Write failing feature tests.
Implement code until tests pass.
Manually check API behavior.
Paste failing and passing terminal output into BEFORE-AFTER.md.
Acceptance criteria mapping
Price storage

Requirement:

POST /api/coach/plans accepts 49.99 and stores 4999.

Approach:

Validate price.
Convert decimal price to integer cents.
Save to price_cents.
Webhook creates payment

Requirement:

POST /api/webhooks/payment records the payment and returns 201.

Approach:

Validate webhook.
Check processor_event_id.
Create payment.
Return 201.
Webhook duplicate event

Requirement:

Sending same event_id again returns 200 and does not duplicate payment.

Approach:

Check existing processor_event_id.
Return 200 if already stored.
Keep unique database index.
January 31st billing

Requirement:

2026-01-31 monthly -> 2026-02-28

Approach:

Use anchor day.
Use non-overflow month calculation.
Fall back to target month's last day.
Client ownership

Requirement:

GET /api/client/subscriptions returns only authenticated client's own subscriptions.

Approach:

Query subscriptions where client_id = auth()->id().
Cancellation

Requirement:

Cancel sets status to cancelled and ends_at to end of current billing period.

Approach:

Find subscription by id and client_id.
Calculate current period end.
Update status and ends_at.
Webhook auth

Requirement:

Webhook endpoint has no auth.

Approach:

Leave route outside auth:sanctum.
Document that production should use signed webhook verification.
Proration

Requirement:

Proration is out of scope.

Approach:

Do not implement plan changes, partial charges, refunds, or credits.
Document this clearly.