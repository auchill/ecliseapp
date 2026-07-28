# Unified Payments: Domain Architecture

## Decision

The payment module will use a single `payments` table for both repair and shop money movement, with source, purpose, method, provider, invoice, transaction, refund, and webhook ledgers layered around it.

The migration is staged:

1. Add non-destructive domain tables and nullable columns.
2. Backfill existing payments.
3. Start writing transactions and webhook events.
4. Add invoice snapshots.
5. Add manual payments and refunds.
6. Tighten validation and reporting once legacy rows are verified.

## Core Tables

- `payments`: canonical payment record across repair and shop flows.
- `payment_transactions`: immutable-ish gateway/manual transaction attempts and results.
- `payment_refunds`: refund lifecycle records.
- `payment_webhook_events`: provider event IDs and sanitized payloads for replay protection.
- `invoices`: customer-facing payable snapshots.
- `invoice_items`: invoice line snapshots.

## Payment Classification

`source` answers where the payment belongs:

- `repair`
- `shop`

`purpose` answers why money is being collected:

- `deposit`
- `balance`
- `full_payment`
- `diagnostic_fee`
- `additional_charge`
- `shop_order`
- `shipping`
- `adjustment`
- `refund`

`method` answers how the customer paid:

- `stripe`
- `paypal`
- `interac`
- `cash`
- `debit_terminal`
- `credit_terminal`
- `pay_in_store`
- `store_credit`
- `gift_card`

`provider` answers what processor or handling channel owns the transaction:

- `stripe`
- `paypal`
- `manual`
- `terminal`

## Compatibility

Existing `paid` payment statuses remain valid as `PaymentStatus::LegacyPaid`. New payment code can move toward `succeeded` after existing UI, reports, and data are fully migrated.

Existing full-class polymorphic values such as `App\Models\Order`, `App\Models\Repair`, and `App\Models\Cart` are preserved. Introducing morph aliases should be handled in a later data migration.
