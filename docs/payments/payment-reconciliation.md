# Payment Reconciliation

Run local reconciliation with:

```bash
php artisan eclise:reconcile-payments --dry-run
```

Useful filters:

```bash
php artisan eclise:reconcile-payments --provider=stripe --from=2026-07-01 --to=2026-07-31
php artisan eclise:reconcile-payments --payment=PAY-2026-0000001 --json
```

Local checks detect:

| Code | Meaning |
| --- | --- |
| `missing_paid_at` | Settled payment with no `paid_at`. |
| `missing_transaction` | Settled payment with no transaction ledger row. |
| `missing_receipt` | Settled payment with no receipt number. |
| `missing_gateway_reference` | Settled gateway payment with no provider reference. |
| `manual_missing_receiver` | Settled manual payment with no receiving staff member. |
| `invoice_balance_mismatch` | Stored invoice balance differs from the derived balance. |
| `invoice_overpaid` | Settled payments exceed the invoice total; a refund may be required. |
| `refund_total_mismatch` | `payments.refunded_amount` differs from the refund ledger. |
| `duplicate_payment_intent` | More than one local payment references one Stripe PaymentIntent. |

Stripe reconciliation:

- With no Stripe secret, the command reports Stripe remote verification as unavailable. PayPal being unavailable never fails a Stripe run.
- With `--provider=stripe` and `STRIPE_SECRET` configured, the command reads each payment's Stripe PaymentIntent and reports:

| Code | Meaning |
| --- | --- |
| `amount_mismatch` | Provider amount differs from the local amount. |
| `currency_mismatch` | Provider currency differs from the local currency. |
| `provider_paid_local_unpaid` | Stripe succeeded while the local payment is unsettled. |
| `local_paid_provider_unpaid` | The local payment is settled while Stripe has not succeeded. |
| `refund_mismatch` | Stripe's refunded total differs from the local refund ledger. |
| `missing_provider_reference` | Settled local payment with no PaymentIntent to verify against. |
| `unknown_provider_reference` | Stripe returned 404 for the stored PaymentIntent. |
| `provider_lookup_failed` | The Stripe lookup failed for another reason. |

- Remote checks are read-only. `--dry-run` writes no audit rows and makes no corrections.
- Provider success is never fabricated: a missing or failed lookup is reported, never assumed.

This stage does not perform automated financial corrections. `--repair` remains reserved; when it is implemented it must confirm provider identity, amount and currency, lock the payment and invoice, preserve history, and audit the correction — and must never invent payments, mark a payment paid without provider evidence, fabricate refund confirmations, or delete records.
