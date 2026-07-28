# Unified Payments: Database Schema

## Added Tables

### `invoices`

Stores customer-facing invoice snapshots, totals, balance state, customer relationship, and invoiceable model relationship.

### `invoice_items`

Stores invoice line snapshots. Lines are intentionally denormalized so product, repair, shipping, and fee labels remain stable after catalog changes.

### `payment_transactions`

Stores payment attempts/results such as payment, authorization, capture, failure, void, refund, chargeback, manual confirmation, and reconciliation events.

### `payment_refunds`

Stores refund lifecycle and approval fields separately from the original payment.

### `payment_webhook_events`

Stores provider webhook event IDs, event type, status, attempts, received/processed timestamps, and sanitized payloads.

## Added `payments` Columns

- `payment_number`
- `invoice_id`
- `customer_id`
- `purpose`
- `method`
- `provider`
- `subtotal`
- `tax_amount`
- `fee_amount`
- `discount_amount`
- `refunded_amount`
- `gateway_payment_id`
- `gateway_reference`
- `gateway_customer_id`
- `gateway_payment_method_id`
- `idempotency_key`
- `authorized_at`
- `failed_at`
- `cancelled_at`
- `refunded_at`
- `received_by`
- `verified_by`
- `verified_at`
- `failure_code`
- `failure_message`
- `admin_note`
- `customer_note`
- `metadata`

## Backfill Rules

- `payment_number` is generated as `PAY-YYYY-0000001`.
- `method` defaults to the existing gateway.
- `provider` defaults to `stripe`, `paypal`, or `manual`.
- repair payments default to `purpose = balance`.
- shop payments default to `purpose = shop_order`.
- `customer_id` is resolved from repair, order, cart, or checkout snapshot where possible.
