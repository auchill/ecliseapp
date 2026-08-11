# eclisetech.ca — Payment Acceptance Runbook

How to run payment acceptance against the deployed site at `eclisetech.ca`, in both **sandbox** (test keys) and **live** (production keys) mode.

This is the deployment-facing companion to [payment-stage-3-stripe-sandbox-acceptance.md](payment-stage-3-stripe-sandbox-acceptance.md), which covers the same scenarios on a local machine.

> **Live mode moves real money.** Every live scenario below uses your own card and is refunded afterwards. Run the whole sandbox pass on the deployed site first, and only then repeat the short live subset in section 6.

---

## 1. What differs from local testing

| | Local | eclisetech.ca |
| --- | --- | --- |
| Webhook delivery | `stripe listen` CLI forwarding | A **dashboard webhook endpoint** at `https://eclisetech.ca/webhooks/stripe` |
| Signing secret | Printed by `stripe listen`, changes per machine | The endpoint's own `whsec_…` from the dashboard — **a different value** |
| Keys | `pk_test_` / `sk_test_` | Sandbox: `pk_test_` / `sk_test_`. Live: `pk_live_` / `sk_live_` |
| HTTPS | Not required | **Required.** Readiness reports `HTTPS callbacks` unmet in production over http:// |
| Queue | Run by hand | Must be supervised, or receipts and admin notifications silently never send |

The single most common failure is using the CLI's signing secret against the deployed site, or the dashboard secret locally. Both produce `400 Invalid Stripe signature` on every event and look exactly like a broken integration.

---

## 2. Prerequisites

1. `APP_URL=https://eclisetech.ca` and the certificate is valid.
2. `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` set for the mode you are testing. Publishable and secret keys **must be the same mode** — readiness rejects a mixed pair.
3. Webhook endpoint registered at `https://eclisetech.ca/webhooks/stripe` with the events in section 3.
4. The endpoint's API version in the Stripe dashboard matches `STRIPE_API_VERSION` in `config/services.php`.
5. `php artisan config:clear` after any environment change.
6. A supervised queue worker (`php artisan queue:work`), and the scheduler if reconciliation is scheduled.
7. Admin → Payments → Settings shows readiness **`ready`** with the expected mode (`Test` or `Live`).
8. At least two admin accounts — refund approval refuses to let a requester approve their own refund.
9. A customer account you control.

Verify before starting:

```bash
php artisan eclise:verify-payment-migration --json
php artisan eclise:reconcile-payments --dry-run --json
```

Both should report zero blockers and zero issues on a clean deployment.

---

## 3. Required webhook events

```
checkout.session.completed
checkout.session.async_payment_succeeded
checkout.session.async_payment_failed
checkout.session.expired
payment_intent.succeeded
payment_intent.payment_failed
refund.updated
charge.refunded
charge.dispute.created
charge.dispute.closed
```

`refund.updated` is **mandatory, not optional**. Current Stripe API versions do not embed the refund list on the charge object, so `charge.refunded` alone will never sync a refund. This was confirmed on live sandbox traffic: `charge.refunded` arrives and is correctly `ignored`, while `refund.updated` does the work.

---

## 4. Sandbox pass on eclisetech.ca

Run all of these with **test keys** on the deployed site. Test cards: `4242 4242 4242 4242` succeeds, `4000 0000 0000 0002` declines, `4000 0000 0000 9995` insufficient funds. Any future expiry, any CVC.

After each scenario, confirm state in Admin → Payments and Admin → MobileSentrix → Procurement Cart.

### S1 — Shop payment, mixed basket

Cart one Eclise product **and** one MobileSentrix device, pay with Stripe.

- Payment `paid` with a receipt number; exactly **one** `payment` transaction
- Invoice `paid`, balance `0.00`
- Order created, inventory committed, cart and its items deleted
- **Procurement buffer gains the MobileSentrix device only** — the Eclise product must not appear
- Customer receipt and admin notification delivered (requires the queue worker)

### S2 — Shop payment declined

Same basket, card `4000 0000 0000 0002`.

- Stripe shows the decline; the customer stays on Stripe and may retry
- Payment stays `pending`; invoice untouched and still payable
- No receipt, no `succeeded` transaction, no order, no procurement row
- **Failure indicator:** any invoice balance movement, or an order appearing

### S3 — Customer abandons checkout

Start Stripe Checkout, then use Stripe's back link.

- Payment `cancelled`, `cancelled_at` set, one `void` transaction
- Invoice unchanged and still payable; **no duplicate invoice or order**
- A new Stripe attempt is allowed and creates a **fresh** session
- **Failure indicator:** being returned to a dead "You're all done here" page, or a retry that silently lands on the same expired session. Both were real defects. A stored session URL is now re-checked with Stripe before reuse, and the idempotency key incorporates the session being superseded — otherwise Stripe replays its cached response and hands back the dead session, because its 24-hour idempotency window outlives the session

### S4 — Repair deposit

Accept a repair proposal choosing a **real part option**, pay the deposit.

- Payment `paid`, deposit invoice `paid`
- Repair `amount_paid` up, `balance_due` down, status `partially_paid` (or `paid` for a full-payment deposit policy)
- Deposit gate satisfied: `repair_in_progress` now permitted
- **Procurement buffer gains a Part row carrying the repair number**

### S5 — Repair final balance

Pay the remaining balance.

- Invoice `paid`, balance `0.00`, deposit **not** charged again
- Repair `payment_status=paid`; pickup/delivery gate satisfied
- **Failure indicator:** balance exceeding the invoice total, or pickup allowed with a balance outstanding

### S6 — Repair additional charge

Admin proposes an additional charge, customer approves, customer pays.

- Additional-charge invoice `paid`; **prior invoices untouched**
- **Failure indicator:** an administrative total change bypassing customer approval

### S7 — "I Have the Parts"

Accept a proposal choosing the customer-supplied option, then pay.

- Payment settles normally
- **No procurement row is created.** This is the rule that must never regress

### S8 — Webhook arrives before the browser returns

Close the tab immediately after paying.

- Payment already `paid` when the success page is opened
- **Failure indicator:** a second finalization triggered by the browser return

### S9 — Browser returns before the webhook

Normal redirect, refresh the success page immediately.

- Shows a **processing** message, never a false failure
- Payment becomes `paid` only when the signed webhook arrives
- **Failure indicator:** the success page marking anything paid

### S10 — Duplicate webhook

`stripe events resend <event_id>` for a settled payment.

- `200` with `duplicate: true`; still one webhook row for that event id
- No second transaction, receipt, invoice movement, refund or notification

### S11 — Forged and stale webhooks

POST an unsigned body; then a correctly signed body with a timestamp an hour old.

- Both rejected `400`
- **Neither is stored.** Nothing reaches `PaymentFinalizer`

### S12 — Amount and currency mismatch

Deliver a correctly signed event whose `amount_total` or `currency` differs from the local payment.

- `422`, so Stripe retries and the discrepancy stays visible
- Payment unchanged, invoice balance unchanged
- Webhook row `failed`, error naming expected vs provider values, visible in Admin → Payment Webhooks
- **Failure indicator:** any finalization, or a fabricated correcting payment

### S13 — Full refund

Request → approve (second admin) → process.

- Stripe refund created **before** local success is recorded
- Payment `refunded`, invoice `refunded`
- **Order/repair not automatically cancelled**

### S14 — Partial refund

- Payment `partially_refunded`; refundable balance correctly reduced
- Repeat for a second partial and confirm the total is right

### S15 — Refund guards

- A refund exceeding the refundable balance is rejected
- A requester cannot approve their own refund
- No second Stripe refund is created in either case

### S16 — Interac, pay-in-store and manual comparison

- Interac sits `pending_verification` until an admin verifies, then settles through the same boundary
- Pay-in-store is offered for pickup only
- A manual payment settles with receipt, transaction, invoice sync and audit
- After any method settles an invoice, a Stripe attempt on it is refused with "no balance due"

### S17 — Procurement follow-through

In Admin → MobileSentrix → Procurement Cart:

- Select part of a multi-unit requirement and create an order → the remainder stays **Pending**
- Order the rest → the requirement becomes **Processed**
- Record the supplier reference, tracking and costs; mark **Received**
- Buffer rows are never deleted; the order traces back to the customer, order number and repair number
- Line prices are the **MobileSentrix cost**, not the customer's marked-up price, and do not change if the catalogue price later changes

---

## 5. Closing the sandbox pass

```bash
php artisan eclise:verify-payment-migration --json
php artisan eclise:reconcile-payments --dry-run --json
php artisan eclise:reconcile-payments --provider=stripe --dry-run --json
```

Expected: zero blockers, zero local issues, zero Stripe discrepancies.

Deliberate test artefacts will remain — the S12 mismatch events stay `failed` by design. Retry or annotate them from Admin → Payment Webhooks so the operational view starts clean.

---

## 6. Live pass

Only after the full sandbox pass is green. Swap to live keys, register the **live** webhook endpoint, take its signing secret, `php artisan config:clear`, and confirm readiness shows mode **Live**.

Use your own card, smallest practical amounts, and refund each afterwards.

1. **Low-value shop purchase** — full S1 checklist, including the procurement row
2. **Low-value repair deposit** — full S4 checklist
3. **Repair final balance** — confirm the deposit is not charged again
4. **Partial refund** — confirm Stripe confirms before local state changes
5. **Receipt review** — renders correctly, contains no provider internals
6. **Stripe reconciliation** — `--provider=stripe --dry-run --json` reports no discrepancies
7. **Refund the remaining live charges** and confirm balances return to zero

Then verify operationally:

- Webhook deliveries all `200` in the Stripe dashboard
- No secret, card number or full provider payload in application logs
- Alerting in place on `payment_webhook_events.status = 'failed'`
- Queue worker supervised and processing

---

## 7. Rollback

- Disable Stripe in Admin → Payments → Settings. Customer-facing Stripe disappears immediately; Interac, cash, terminal and pay-in-store keep working
- Disable the dashboard webhook endpoint only if events must be paused. Stripe retries for roughly three days, so events are not lost
- Refund through the **admin refund workflow**, not the Stripe dashboard, so the local ledger stays authoritative
- Keep the Stripe account owner and escalation contact identified before go-live

---

## 8. Known gaps

- **Wallets.** Stripe Checkout may offer Apple Pay and Google Pay. Eclise does not disable them and implements no separate wallet SDK. Availability depends on account settings, registered domains, device and browser. Not claimed as tested — that needs real device and browser testing.
- **PayPal.** Deferred. Not part of acceptance, hidden from customers, and never a blocker for Stripe.
- **Two tabs on one cart.** Two checkouts against the same *cart* still create two payment and invoice pairs. Only same-invoice attempts are deduplicated. Overpayment is detectable via the `invoice_overpaid` reconciliation code but is not prevented at initiation.
- **Webhook payload shape** follows the API version set on the endpoint in the dashboard, which the pinned request header does not control.

---

## 9. Deploy note: Checkout payload changes

The Stripe idempotency key is derived from the Checkout Session payload. Changing that payload — adding a field, altering `success_url`, changing the product description — produces a different key, which is deliberate: Stripe **rejects** a reused key whose parameters changed, and a key fixed to the payment id would dead-end every payment that was mid-flight across the deploy.

After any deploy that alters the Checkout payload, spot-check that a pending payment can still start a session. A payment stuck at `failed` with a `failure_message` mentioning "Keys for idempotent requests" is this class of problem.
