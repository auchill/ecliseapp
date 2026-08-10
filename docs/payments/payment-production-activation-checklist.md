# Payment Production Activation Checklist

Stripe is the only gateway covered by this activation checklist. PayPal remains deferred and must be activated in a later dedicated stage. Do not enable live Stripe automatically — activation requires the formal approval in step 32.

## Pre-Activation

1. **Verified database backup** taken and restore tested.
2. **Migrations verified** — `php artisan migrate:status` shows the payment domain and Stage 2 operational migrations as run.
3. **Payment verifier passes** — `php artisan eclise:verify-payment-migration --json` reports zero blockers.
4. **Local reconciliation passes** — `php artisan eclise:reconcile-payments --dry-run --json` reports zero local discrepancies.
5. **Stripe reconciliation passes** — `php artisan eclise:reconcile-payments --provider=stripe --dry-run --json` reports zero discrepancies against sandbox data.
6. **Full automated suite passes** — `php artisan test`.

## Stripe Account and Credentials

7. **Stripe account production readiness** — business details, bank account and identity verification complete; the account is out of test-only mode.
8. **Live publishable key** — `STRIPE_KEY` set to `pk_live_…`.
9. **Live secret key** — `STRIPE_SECRET` set to `sk_live_…`. Never commit it; never expose it to Blade or JavaScript.
10. **Live webhook secret** — `STRIPE_WEBHOOK_SECRET` set to the live endpoint's `whsec_…`. This differs from the Stripe CLI secret used locally.
11. **Live webhook endpoint registered** at `https://<production-host>/webhooks/stripe` with the events listed below.
12. **HTTPS verified** — `APP_URL` uses `https://`. Readiness reports `HTTPS callbacks` as unmet in production otherwise.
13. **Stripe domain requirements verified** — any wallet domains registered in the Stripe dashboard.
14. **CAD currency confirmed** — `default_currency` is CAD in Payment Settings; Stripe checkout refuses anything else.
15. **Tax configuration reviewed** — the 13% shop tax rate and repair tax handling match current obligations.
16. **Invoice configuration reviewed** — `invoice_due_days` and `invoice_terms`.
17. **Receipt configuration reviewed** — `automatic_receipt_email` and receipt numbering.
18. **Refund permissions reviewed** — `refund_approval_required` and `refund_approval_threshold`; confirm a requester cannot approve their own refund.
19. **Admin permissions reviewed** — only intended staff reach the payment, refund, settings and webhook screens.
20. **Interac configuration reviewed** — recipient name, recipient email and instructions are correct.
21. **Pay-in-store configuration reviewed** — `allow_pay_in_store_for_pickup` matches store policy.

Run `php artisan config:clear` after every environment change, then confirm Admin > Payments > Settings shows Stripe readiness `ready` with mode `Live`.

### Required Webhook Events

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `refund.updated`
- `charge.refunded`
- `charge.dispute.created`
- `charge.dispute.closed`

`refund.updated` is required: Stripe no longer embeds the refund list on the charge object by default, so `charge.refunded` alone will not sync refunds. Set the endpoint's API version in the Stripe dashboard to match `STRIPE_API_VERSION` in `config/services.php`.

## Operations

22. **Queue workers verified** — receipt and admin notification mailables are queued; a stalled worker means silent non-delivery. Supervise `php artisan queue:work`.
23. **Scheduler verified** — running if reconciliation is scheduled.
24. **Logging reviewed** — confirm no secret, card number or full provider payload reaches the logs.
25. **Webhook failure monitoring reviewed** — alerting on `payment_webhook_events.status = failed`, plus the Stripe dashboard's failed-delivery view.

## Live Smoke Tests

26. **Low-value live shop transaction** — smallest practical amount, real card, verify end to end.
27. **Low-value live repair deposit.**
28. **Live repair final-balance payment** — confirm the deposit is not charged again.
29. **Live partial refund** — confirm Stripe confirms before local state changes.
30. **Receipt review** — the customer receipt renders correctly and contains no provider internals.
31. **Stripe reconciliation** — `--provider=stripe --dry-run --json` against live data reports no discrepancies.

## Go-Live

32. **Formal go-live approval** recorded by the business owner.
33. **Incident and rollback procedure** documented and understood:
    - Disable Stripe in Admin > Payments > Settings (customer-facing Stripe disappears immediately; manual and Interac methods keep working).
    - Disable the Stripe webhook endpoint in the Stripe dashboard if events must be paused. Stripe retries for roughly three days, so events are not lost.
    - Refund affected charges through the admin refund workflow rather than the Stripe dashboard, so the local ledger stays authoritative.
    - Escalation contact and Stripe account owner identified.

## PayPal

**PayPal production activation: deferred to a future stage.** PayPal must not block Stripe production readiness. No PayPal webhook ID has been fabricated and no PayPal testing is claimed.

## Final Local Verification

```bash
php artisan config:clear
php artisan eclise:verify-payment-migration --json
php artisan eclise:reconcile-payments --dry-run --json
php artisan eclise:reconcile-payments --provider=stripe --dry-run --json
php artisan test
./vendor/bin/pint --test
npm run build
php artisan route:list
```

## Environment Note

Node 22.3.0 currently builds successfully. Vite prefers Node 20.19+ or 22.12+. Upgrading to a current Node 22 release is recommended but is **not** a Stage 3 payment blocker while the build succeeds.
