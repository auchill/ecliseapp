# ADR 0008: Reconciliation Boundary

Date: 2026-07-28

## Status

Accepted

## Context

The system needs a reconciliation command, but automated financial repair is risky.

## Decision

Implement `eclise:reconcile-payments` as a local dry-run reporting command. Reserve `--repair` for future controlled correction work and do not mutate financial rows in Stage 2.

## Consequences

Operators get visibility without accidental financial rewrites.
