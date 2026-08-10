# Refund Workflow

Refunds use `payment_refunds` and do not delete payments.

Flow:

1. Admin requests a refund from a paid payment.
2. A second admin approves if approval is required.
3. Admin processes the refund.
4. Payment refunded amount and status are updated.
5. A refund transaction row is written.
6. The invoice balance is resynchronized.

Refund statuses include `pending`, `approved`, `processing`, `succeeded`, and failure states from the `RefundStatus` enum.

Stripe refunds:

- Stripe refunds call Stripe's refund API when the payment provider is `stripe`.
- The Stripe refund request uses a stable idempotency key based on the refund number.
- Local payment balances are updated only after Stripe returns a successful refund status.
- A pending Stripe refund remains `processing` locally until a later successful provider result/webhook updates it.
- Failed Stripe refund responses are stored on the refund record and do not change the local paid balance.

Refund state synchronization from webhooks:

- `refund.updated` is the authoritative provider signal and must be enabled on the Stripe endpoint.
- `charge.refunded` is handled as a best-effort fallback that syncs every refund embedded in the charge. Current Stripe API versions no longer embed the refund list on the charge object by default, so `charge.refunded` alone is not sufficient.
- Refund sync is idempotent: replaying an event for a refund already in the target state changes nothing, and the payment's refunded amount is always recomputed from the refund ledger rather than incremented.
- Refund limits count pending, approved and processing refunds against the refundable balance, so a duplicate request cannot overrun it.

Disputes:

- `charge.dispute.created` and `charge.dispute.closed` record a chargeback transaction and an audit entry for administrator investigation.
- A dispute never reverses a settled payment and never cancels an order or repair. Reversing the payment would reopen the invoice balance and permit a second charge.

Manual refunds keep the existing manual processing path.
