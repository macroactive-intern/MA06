
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true                                                                                                                                  0.01s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                                                      0.16s  

   PASS  Tests\Feature\SubscriptionBillingTest
  ✓ it stores price as integer cents when a coach creates a plan                                                                                       0.11s  
  ✓ it records a new payment and returns 201 on first webhook delivery                                                                                 0.02s  
  ✓ it returns 200 and does not create a duplicate payment on repeated webhook delivery                                                                0.01s  
  ✓ it returns next_billing_date of 2026-02-28 for a monthly subscription started on 2026-01-31                                                        0.01s  
  ✓ it returns next_billing_date of 2026-06-15 for a quarterly subscription started on 2026-03-15                                                      0.01s  
  ✓ it returns only the authenticated clients own subscriptions                                                                                        0.01s  
  ✓ it returns 404 when a client tries to cancel another clients subscription                                                                          0.02s  
  ✓ it cancellation sets status to cancelled and ends_at to the current period end                                                                     0.01s  
  ✓ it returns 404 when a coach tries to update another coachs plan                                                                                    0.01s  
  ✓ it returns 404 when a coach tries to deactivate another coachs plan                                                                                0.01s  
  ✓ it returns only active subscriptions for the authenticated coachs plans                                                                            0.01s  
  ✓ it does not return inactive plans in the public plans listing                                                                                      0.01s  
  ✓ it returns 422 when a client tries to subscribe to an inactive plan                                                                                0.01s  

  Tests:    15 passed (26 assertions)
  Duration: 0.59s

  All 13 tests passed on the first run — no failures to wait on