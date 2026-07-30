# ADR 0005: Manual Payment Recording

Date: 2026-07-28

## Status

Accepted

## Context

Eclise needs to accept Interac, cash, debit terminal, credit terminal, and pay-in-store payments.

## Decision

Manual methods are recorded through invoices. Non-Interac methods are finalized immediately by an admin. Interac is created as `pending_verification` and requires explicit verification or rejection.

## Consequences

Manual payments share the same payment, receipt, invoice, transaction, and audit ledgers as gateway payments.
