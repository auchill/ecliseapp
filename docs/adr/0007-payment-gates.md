# ADR 0007: Payment Gates

Date: 2026-07-28

## Status

Accepted

## Context

Orders and repairs should not move into fulfillment states before required payment is confirmed.

## Decision

Use `PaymentGateService` from admin update flows. Validate submitted fulfillment/status values against confirmed payment state, not against form-submitted payment status.

## Consequences

Admins cannot bypass payment requirements by changing a status form field to `paid`.
