# Subscription Billing API — Production Readiness Audit

**Rubric:** Production-Ready Rubric (Laravel JSON API)
**Date:** 2026-06-19
**Result: 2 / 10 PASS**

---

## Summary

| # | Criterion | Result |
|---|---|---|
| 1 | Type Safety | FAIL |
| 2 | Error Handling | FAIL |
| 3 | Observability | FAIL |
| 4 | Configuration | FAIL |
| 5 | Validation | PASS |
| 6 | Data Integrity | FAIL |
| 7 | Security | FAIL |
| 8 | API Consistency | FAIL |
| 9 | Tests Pass | PASS |
| 10 | No Hardcoded Environment Values | FAIL |

---

## 1. Type Safety — FAIL

`declare(strict_types=1)` is absent from every file under `app/`. All 18 files — controllers, models, services, and requests — are missing it.

Method signatures are otherwise well-typed: parameters and return types are declared throughout. The only remediation needed is adding the strict types declaration to each file.

---

## 2. Error Handling — FAIL

No named exception classes exist. Business logic failures are returned as inline JSON responses rather than thrown as `ValidationException` with a specific key:

- `ClientSubscriptionController.php:22` — plan inactive → `response()->json(['message' => ...], 422)`
- `ClientSubscriptionController.php:31` — duplicate subscription → same pattern

The rubric requires business failures to surface as `ValidationException` (with a named key) or a dedicated exception class, so that monitoring can alert on specific failure types.

`PaymentWebhookService` does catch `QueryException` for duplicate key errors and handles it correctly — that is not a violation — but it re-throws the raw exception on non-duplicate failures rather than wrapping it with context.

---

## 3. Observability — FAIL

Zero `Log::info()` calls exist anywhere in the codebase. None of the state-changing operations emit a structured log entry:

| Operation | File | Log entry |
|---|---|---|
| Plan created | `CoachPlanController@store` | None |
| Plan updated | `CoachPlanController@update` | None |
| Plan deactivated | `CoachPlanController@destroy` | None |
| Subscription created | `ClientSubscriptionController@store` | None |
| Subscription cancelled | `ClientSubscriptionController@cancel` | None |
| Payment recorded | `PaymentWebhookService@process` | None |

---

## 4. Configuration — FAIL

Several values are hardcoded in business logic that should live in `config/*.php`:

- Status strings `'active'`, `'cancelled'`, `'past_due'` appear across controllers and services
- Billing cycle values `'monthly'`, `'quarterly'`, `'annual'` are hardcoded in `BillingDateService.php:10-14` and form requests
- The cycle-to-months mapping (`monthly=1`, `quarterly=3`, `annual=12`) is a static private array in `BillingDateService`, not a config value

No `config()` calls exist anywhere in the application logic.

---

## 5. Validation — PASS

Validation is correctly delegated to Form Request classes. No validation logic lives in controllers. The `exists:subscription_plans,id` rule in `StoreClientSubscriptionRequest` does fire a query, and `ClientSubscriptionController` then calls `findOrFail` — a minor duplicate query — but this is an acceptable boundary concern, not an N+1 pattern at scale.

---

## 6. Data Integrity — FAIL

No `DB::transaction()` calls exist anywhere. No `lockForUpdate()` calls exist anywhere.

The most significant exposure is in `ClientSubscriptionController@store`:

```php
// Check
$alreadySubscribed = Subscription::where(...)->exists();   // line 25

// Unprotected gap — concurrent request can slip in here

// Write
$subscription = Subscription::create([...]);               // line 34
```

Two simultaneous requests from the same client to the same plan can both pass the existence check and both insert a subscription. There is no unique database constraint on `(client_id, plan_id, status)` to catch this at the database level either.

`PaymentWebhookService` does catch duplicate key exceptions as a race condition guard, but it is not wrapped in a transaction.

---

## 7. Security — FAIL

All non-public endpoints are correctly behind `auth:sanctum` — that part passes. The webhook is intentionally public, which is correct.

The failures are:

- All four Form Request `authorize()` methods return hardcoded `true`. Authorization logic (ownership checks) is done inside controllers instead, which works but is not policy-based as the rubric requires.
- No `Policy` classes exist. Resource mutations (`update`, `destroy`, `cancel`) rely on inline `where('coach_id', auth()->id())` queries rather than `$this->authorize('update', $plan)`.
- No webhook signature verification. The approach doc notes this is acceptable for the brief, but it is a production gap.

---

## 8. API Consistency — FAIL

**Status codes:**

- `DELETE /api/coach/plans/{id}` (deactivation) returns `200` with a body. The rubric requires `204 No Content` for deletes.
- `403` is never returned — ownership failures return `404` intentionally (to avoid leaking record existence), but this means the rubric's `403` for authorization failures is not present.

**Response shapes:**

List endpoints wrap responses in `data`:

```json
{ "data": [...] }
```

Create, update, cancel, and delete endpoints return the raw model with no wrapper. There are no API Resource classes — controllers return raw Eloquent models and arrays inconsistently.

---

## 9. Tests Pass — PASS

```
Tests: 15 passed (26 assertions)
Duration: 0.59s
```

All 13 feature tests and 2 scaffold tests pass. No tests are skipped or pending.

---

## 10. No Hardcoded Environment Values — FAIL

`.env` is correctly gitignored — no secrets are tracked.

However, `.env.example` line 4:

```
APP_DEBUG=true
```

The rubric requires `APP_DEBUG=false` in the example file. Developers who copy `.env.example` verbatim will deploy with debug mode on, exposing stack traces in HTTP responses.
