# ADR 0002: Invoice Snapshots

Date: 2026-07-28

## Status

Accepted

## Context

Payments need durable customer-facing invoices for both shop checkout and repair workflows. The source cart, order, or repair can change over time.

## Decision

Store invoices and invoice items as local snapshots. Generate invoices in controllers/services before payment handoff or before admin manual payment. Do not call gateways or calculate invoice state from Blade views.

## Consequences

Invoices can be printed and audited without relying on mutable cart, order, or repair details. Paid invoices are not edited in place.
