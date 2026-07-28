# ADR 0001: Payment Domain Foundation

Date: 2026-07-28

## Status

Accepted

## Context

Eclise collects payments for shop orders and repair workflows. Existing checkout and repair payment logic works, but payment records also need invoices, transactions, refunds, webhook replay protection, manual payments, and reconciliation without breaking current customer-facing flows.

## Decision

Keep `payments` as the central payment table and add related domain tables:

- `invoices`
- `invoice_items`
- `payment_transactions`
- `payment_refunds`
- `payment_webhook_events`

Add nullable payment classification and audit columns first, backfill what can be inferred, and keep existing statuses and polymorphic values compatible during the transition.

## Consequences

Current checkout and repair payment flows continue working. New payment records can now write transaction and webhook ledgers. Later stages can add manual payments, invoices, refunds, and reconciliation without replacing the existing working payment path.

The system temporarily supports both `paid` and `succeeded` as successful payment statuses. A later migration can normalize historical data after verification.
