# ADR 0004: Gateway Webhook Authority

Date: 2026-07-28

## Status

Accepted

## Context

Browser return routes are not reliable proof of gateway payment success.

## Decision

Stripe and PayPal payments are finalized only after signed webhook confirmation. PayPal browser return may capture a PayPal order but leaves the local payment in a non-paid state until webhook confirmation.

## Consequences

The checkout flow is less optimistic but avoids marking orders paid from spoofable browser redirects.
