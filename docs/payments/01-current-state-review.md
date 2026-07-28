# Unified Payments: Current State Review

Date: 2026-07-28

## Existing Payment Shape

The existing `payments` table already acts as the payment bridge for both repairs and shop checkout. It stores `payable_type`, `payable_id`, `order_id`, `repair_id`, `source`, gateway identifiers, `amount`, `currency`, `status`, `checkout_data`, `raw_response`, and `paid_at`.

Current sources are:

- `repair`
- `shop`

Current gateways are:

- `stripe`
- `paypal`

The existing success status in production code is `paid`. The new domain enum also supports `succeeded`, but keeps `paid` as a legacy-success status so existing records and tests continue to work during the staged migration.

## Existing Flow Summary

Shop checkout creates a pending payment against a cart snapshot. `PaymentFinalizer::markPaid()` converts the cart snapshot into an order, creates order items, commits inventory, creates a status update, deletes the cart, and sends receipt/admin emails.

Repair payment creation is already server-calculated from repair balances and proposals. `PaymentFinalizer::markPaid()` updates repair amount paid, balance due, payment status, status updates, and repair-conversation state when applicable.

Stripe and PayPal webhooks verify signatures and call the finalizer, but previously did not persist webhook event IDs or transaction ledger rows.

## Gaps Found

- No `payment_transactions` ledger.
- No `payment_refunds` table.
- No `invoices` or `invoice_items` snapshot tables.
- No persistent webhook-event replay protection.
- Gateway payloads were stored directly on `payments.raw_response`.
- No manual payment workflow.
- No reconciliation command.
- No invoice/refund admin UI.
- No dedicated payment architecture documentation.

## Existing Pieces Reused

- Polymorphic `Payment` relationship.
- `source = repair|shop` distinction.
- `PaymentFinalizer` idempotent checkout finalization.
- Server-side cart and repair total calculation.
- Customer-owned carts/orders/repairs.
- Existing Stripe and PayPal gateway service.
- Existing receipt and admin payment emails.
