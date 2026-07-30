# ADR 0003: Repair Deposit Policy

Date: 2026-07-28

## Status

Accepted

## Context

Repair deposits must be configurable without changing code.

## Decision

Store repair deposit behavior in `payment_settings` and calculate deposits with `PaymentSettingsService`. Keep the default as `full_payment` to preserve existing behavior.

## Consequences

The business can move to fixed, percentage, minimum, or no-deposit policy later without schema changes.
