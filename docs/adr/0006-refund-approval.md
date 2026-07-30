# ADR 0006: Refund Approval

Date: 2026-07-28

## Status

Accepted

## Context

Refunds need auditability and separation from payment deletion.

## Decision

Create refund rows with request, approval, and processing state. When approval is required, the requester cannot approve their own refund.

## Consequences

Refund activity remains inspectable and invoice balances can be recalculated from the ledger.
