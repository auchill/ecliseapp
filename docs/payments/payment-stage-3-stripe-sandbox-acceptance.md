# Stage 3 Stripe Sandbox Acceptance

This stage hardens Stripe only. PayPal code is left in place but is not activated, tested, or claimed complete in this stage. See [PayPal deferral](#paypal-deferral) below.

## Architecture Confirmed in This Stage

| Question | Answer |
| --- | --- |
| Checkout Sessions or Payment Intents? | Stripe Checkout Sessions (hosted). No card fields are rendered by Eclise, so PCI scope stays at SAQ-A. |
| Gateway transport | Laravel HTTP client through `App\Services\Payments\StripeApiClient` (pinned `Stripe-Version`, timeouts, bounded retries). No Stripe SDK dependency. |
| Amount authority | Server only. `PaymentBalanceService` derives the balance; request input never sets amounts. |
| Idempotency | Stripe `Idempotency-Key` on Checkout Session and refund creation, plus a local `payments.idempotency_key`. |
| Signature verification | HMAC-SHA256 with timestamp tolerance, verified before anything is persisted. |
| Event persistence | Verified events are stored in `payment_webhook_events` before processing. |
| Duplicate handling | Deduplicated by `(provider, provider_event_id)`; processed/ignored events short-circuit. |
| Finalization boundary | `PaymentFinalizer::markPaid` only. No controller duplicates it. |
| Invoice sync | `PaymentBalanceService::synchronizeInvoice` after every successful finalization and refund. |
| Receipts | `ReceiptService::ensureReceiptNumber` inside the finalization transaction. |
| Notifications | Queued mailables, dispatched after the transaction commits. |
| Error sanitization | `PaymentPayloadSanitizer` redacts sensitive keys from every stored payload. |
| Credential separation | Readiness enforces matching `_test_` / `_live_` key modes. |
| Refunds | Stripe refunds are submitted before local refund success is recorded. |
| PayPal unavailable | Stripe, Interac, Cash, Debit, Credit and Pay-in-Store all function with PayPal absent. |

## Stripe Events Processed

Eclise processes exactly these events (`StripeWebhookProcessor::HANDLED_EVENT_TYPES`). Anything else is acknowledged with `200` and stored as `ignored`.

| Event | Effect |
| --- | --- |
| `checkout.session.completed` | Finalize **only** when `payment_status` is `paid` or `no_payment_required`; otherwise record references and set `processing`. |
| `checkout.session.async_payment_succeeded` | Same as above. |
| `payment_intent.succeeded` | Finalize. Idempotent, so it is safe alongside `checkout.session.completed`. |
| `checkout.session.expired` | Mark the attempt `expired`. Never applied to a settled payment. |
| `checkout.session.async_payment_failed` | Mark the attempt `failed`. |
| `payment_intent.payment_failed` | Mark the attempt `failed`. |
| `refund.updated` | Authoritative refund state sync. |
| `charge.refunded` | Best-effort sync of every embedded refund. Stripe no longer embeds the refund list by default, so `refund.updated` must also be enabled. |
| `charge.dispute.created` | Record a chargeback transaction and audit entry. Does **not** reverse the payment. |
| `charge.dispute.closed` | Record the closing state. Does **not** reverse the payment. |

Only one financial finalization occurs per payment even when Stripe delivers both `checkout.session.completed` and `payment_intent.succeeded`, because `PaymentFinalizer::markPaid` returns early for an already-settled payment.

## Prerequisites for Every Scenario

- Sandbox `STRIPE_KEY` (`pk_test_…`), `STRIPE_SECRET` (`sk_test_…`), `STRIPE_WEBHOOK_SECRET` (`whsec_…`) configured in `.env`.
- `php artisan config:clear` run after any environment change.
- Admin > Payments > Settings shows Stripe readiness `ready`.
- Webhook delivery available (Stripe CLI forwarding or a public tunnel). Without it, payments correctly remain `pending`.
- Queue worker running (`php artisan queue:work`) so receipt and admin notifications are delivered.
- A customer account and an admin account.
- Stripe sandbox test cards only. Never use real card details.

Reference cards: `4242 4242 4242 4242` (success), `4000 0000 0000 0002` (declined), `4000 0000 0000 9995` (insufficient funds).

---

## 1. Shop Successful Payment

| Aspect | Expectation |
| --- | --- |
| Customer action | Add items to cart, checkout, choose Stripe, pay with `4242…4242`. |
| Admin action | None required. |
| Browser result | Redirect to the success route showing "Payment will be confirmed by webhook", then Paid once the webhook lands. |
| Payment record | `status=paid`, `paid_at` set, `stripe_checkout_session_id`, `stripe_payment_intent_id`, `receipt_number` present. |
| Invoice | `status=paid`, `balance_due=0`, `amount_paid` equals the total. |
| PaymentTransaction | Exactly one `payment` / `succeeded` row. |
| PaymentWebhookEvent | One `stripe` row, `status=processed`, sanitized payload, `attempt_count=1`. |
| Receipt | Receipt number generated and viewable at the receipt route. |
| Audit | `payment.succeeded` entry. |
| Notification | Customer receipt email plus admin notification, both queued. |
| Reconciliation | `--provider=stripe --dry-run` reports zero discrepancies. |
| Failure indicators | Two `payment` transactions; `paid` without a receipt number; invoice balance unchanged. |

## 2. Shop Failed Payment

| Aspect | Expectation |
| --- | --- |
| Customer action | Checkout with Stripe, pay with `4000 0000 0000 0002`. |
| Browser result | Stripe shows the decline; the customer may retry. |
| Payment record | Stays `pending` (Stripe does not complete the session), or `failed` if an explicit failure event arrives. |
| Invoice | Unchanged and still payable. |
| PaymentTransaction | No `succeeded` payment row. A `failure` row only if a failure event was delivered. |
| PaymentWebhookEvent | Only for events Stripe actually delivered. |
| Receipt | None. |
| Reconciliation | No discrepancy; the payment is simply unsettled. |
| Failure indicators | Any invoice balance movement; any receipt number. |

## 3. Shop Checkout Cancellation

| Aspect | Expectation |
| --- | --- |
| Customer action | Start Stripe Checkout, then use Stripe's back link. |
| Browser result | Cancel route renders "Payment was cancelled. No order was marked paid." |
| Payment record | `status=cancelled`, `cancelled_at` set. Previously successful payments are never touched. |
| Invoice | Unchanged, still payable; **no** duplicate invoice created. |
| Order/Repair | No duplicate order, no duplicate deposit invoice. |
| PaymentTransaction | One `void` row. |
| Retry | A new Stripe attempt is allowed. |
| Failure indicators | Duplicate invoice or order; the cancel route flipping an already-paid payment. |

## 4. Repair Deposit

| Aspect | Expectation |
| --- | --- |
| Prerequisite | Repair proposal accepted by the customer. Deposit policy set in Payment Settings (`none`, `fixed`, `percentage`, `minimum`, `full_payment`). |
| Customer action | Open the repair conversation, choose Stripe, pay the deposit. |
| Payment record | `purpose=deposit`, `status=paid` after the webhook. |
| Invoice | Deposit invoice `paid`; the repair's final invoice still reflects the remaining balance. |
| Repair | `amount_paid` increased, `payment_status=partially_paid` (or `paid` for a full-payment deposit), `balance_due` reduced. |
| Gate | With `require_repair_deposit_before_work` enabled, `repair_in_progress` is now permitted. |
| Failure indicators | Work permitted before deposit settlement; deposit charged twice; deposit not reflected in the final balance. |

## 5. Repair Final Balance

| Aspect | Expectation |
| --- | --- |
| Prerequisite | Repair in final billing state with a deposit already paid. |
| Customer action | Pay the remaining balance with Stripe. |
| Payment record | `purpose=balance`, `status=paid`. |
| Invoice | `balance_due=0`, deposits already counted, `status=paid`. |
| Repair | `payment_status=paid`, `paid_at` set. |
| Gate | `ready_for_pickup` / `shipped` / `completed` now permitted per fulfillment method. |
| Failure indicators | Deposit charged again; balance exceeding the invoice total; pickup allowed while a balance remains. |

## 6. Repair Additional Charge

| Aspect | Expectation |
| --- | --- |
| Prerequisite | Additional charge proposed **and** customer-approved. |
| Customer action | Pay the additional-charge invoice with Stripe. |
| Invoice | Additional-charge invoice `paid`; prior invoices untouched. |
| Failure indicators | An administrative total change bypassing customer approval; the original invoice being mutated. |

## 7. Webhook Arrives Before Browser Return

| Aspect | Expectation |
| --- | --- |
| Setup | Delay the Stripe redirect (or close the tab) so the webhook lands first. |
| Payment record | Already `paid` when the success page loads. |
| Browser result | Success page shows the confirmed state. |
| Failure indicators | A second finalization triggered by the browser return. |

## 8. Browser Return Before Webhook

| Aspect | Expectation |
| --- | --- |
| Browser result | "Payment processing" style message — **never** a false failure. |
| Payment record | Still `pending`; becomes `paid` only when the signed webhook arrives. |
| Failure indicators | The success page marking the payment paid; a false "failed" state. |

## 9. Duplicate Webhook

| Aspect | Expectation |
| --- | --- |
| Setup | Replay the same event with `stripe events resend <event_id>`. |
| HTTP response | `200` with `{"received":true,"duplicate":true}`. |
| PaymentWebhookEvent | Still exactly one row for that event ID. |
| Financial effect | No second transaction, receipt, invoice movement, refund, or notification. |

## 10. Invalid Webhook

| Aspect | Expectation |
| --- | --- |
| Setup | POST an unsigned body, a wrong signature, or a signature older than 300 seconds. |
| HTTP response | `400`. |
| PaymentWebhookEvent | **No** row stored. |
| Financial effect | None. `PaymentFinalizer` is never called. |
| Diagnostics | A safe log line without payload contents or secrets. |

## 11. Amount or Currency Mismatch

| Aspect | Expectation |
| --- | --- |
| Setup | Deliver a correctly signed event whose `amount_total` or `currency` differs from the local payment. |
| HTTP response | `422`, so Stripe retries and the discrepancy stays visible. |
| Payment record | Unchanged and unsettled. |
| Invoice | Balance unchanged. |
| PaymentWebhookEvent | `status=failed` with an error message naming the expected and provider values. |
| Admin | Visible under Admin > Payments > Webhook Events for investigation. |
| Failure indicators | Any finalization; a fabricated correcting payment. |

## 12. Full Refund

| Aspect | Expectation |
| --- | --- |
| Admin action | Request a refund for the full amount, approve it (a different admin if approval is required), then process it. |
| Provider | A Stripe refund is created before local success is recorded, using idempotency key `stripe-refund-{refund_number}`. |
| PaymentRefund | `status=succeeded`, `provider_refund_id` stored. |
| Payment | `status=refunded`, `refunded_amount` equals the payment amount. |
| Invoice | `status=refunded`, refunded amount synchronized. |
| Order/Repair | **Not** automatically cancelled. |
| Failure indicators | Local success recorded before Stripe confirmed; the refund exceeding the payment. |

## 13. Partial Refund

| Aspect | Expectation |
| --- | --- |
| Admin action | Refund part of the amount, twice if testing multiple partials. |
| Payment | `status=partially_refunded`; `refunded_amount` is the sum of succeeded refunds. |
| Invoice | `status=partially_refunded`. |
| Failure indicators | The sum of refunds exceeding the payment; concurrent requests overrunning the refundable balance. |

## 14. Duplicate Refund Request

| Aspect | Expectation |
| --- | --- |
| Admin action | Attempt a second refund that would exceed the refundable balance. |
| Result | Rejected with "Refund amount exceeds the refundable balance." Pending and approved refunds are counted against the balance. |
| Provider | No second Stripe refund is created. |

## 15. Interac Comparison

| Aspect | Expectation |
| --- | --- |
| Customer action | Choose Interac e-Transfer at checkout. |
| Payment record | `status=pending_verification`, `submitted_at` set. Not paid. |
| Admin action | Verify from Admin > Payments > Pending Verification. |
| After verification | Payment settles, invoice synchronizes, receipt generated — same as Stripe. |
| Cross-method | Verifying Interac after Stripe already settled the invoice must show zero remaining balance. |

## 16. Pay-in-Store Comparison

| Aspect | Expectation |
| --- | --- |
| Availability | Pickup orders only. Rejected for shipping orders. |
| Gate | Shipping stays blocked until confirmed full payment. |

## 17. Manual Payment Comparison

| Aspect | Expectation |
| --- | --- |
| Admin action | Record a cash / debit terminal / credit terminal payment. |
| Result | Finalizes through the same `PaymentFinalizer` boundary with receipt, transaction, invoice sync and audit. |
| Cross-method | After a manual payment settles the invoice, a Stripe attempt on the same invoice is refused with "The linked invoice has no balance due." |

---

## Automated Coverage

```bash
php artisan test tests/Feature/PaymentStageThreeStripeTest.php
php artisan test tests/Feature/PaymentStageThreeStripeHardeningTest.php
```

These suites are fully mocked (`Http::fake`) and never contact Stripe. They cover: money conversion for every documented CAD amount, readiness gating including malformed keys and mixed test/live modes, `Stripe-Version` pinning, Checkout Session idempotency and metadata, signature rejection, replay-tolerance rejection, secret-rotation acceptance, unpaid-session gating, duplicate delivery, double-finalization, unmatched events, unsupported events, late failure events, disputes, refund sync via `refund.updated` and `charge.refunded`, customer ownership, browser-parameter tampering, cancellation, cross-method overpayment refusal, session reuse, admin webhook retry, and reconciliation discrepancy detection.

## Verification Levels

Every claim in a Stage 3 report must be labelled with one of:

- **Implemented** — code exists and is reviewed.
- **Verified (mocked)** — covered by the automated suites above; no Stripe API interaction.
- **Verified (local MySQL)** — verifier and reconciliation commands run against the local database.
- **Verified (real Stripe sandbox)** — an actual Stripe API call occurred. Only claim this when it did.
- **Requires manual browser testing** — the scenarios in this document.
- **Deferred / Blocked** — with the reason.

## PayPal Deferral

PayPal integration is intentionally deferred.

**Reason:** the required PayPal webhook configuration / Webhook ID is not currently available.

- Existing PayPal controllers, services, routes, migrations and tests have been preserved unchanged.
- PayPal is **not** part of Stage 3 acceptance.
- `PAYPAL_WEBHOOK_ID` is not required for application startup, the payment verifier, local reconciliation, Stripe reconciliation, the automated test suite, the payment settings page, shop checkout, repair checkout, or Stage 3 acceptance.
- Admin > Payments > Settings reports PayPal as `deferred` and never as a Stripe readiness failure.
- PayPal is hidden from customer-facing payment options until a future stage activates it.
- No PayPal webhook ID has been fabricated, and no PayPal testing is claimed.

Stripe, Interac, Cash, Debit Terminal, Credit Terminal and Pay-in-Store proceed independently.

## Wallets (Apple Pay / Google Pay)

Stripe Checkout may present Apple Pay and Google Pay automatically. Eclise does not disable them and does not implement separate wallet SDKs. Availability depends on Stripe account settings, payment-method configuration, registered domains, the customer's browser and device, and customer eligibility. No wallet testing is claimed for this stage — that requires supported device and browser testing.
