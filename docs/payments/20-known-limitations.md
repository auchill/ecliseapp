# Unified Payments: Known Limitations

The current implementation is a safe foundation, not the full payment-module rewrite.

Deferred work:

- Manual payment creation UI.
- Manual payment approval/rejection workflow.
- Invoice rendering and customer invoice download.
- Refund request, approval, gateway refund, and refund notification UI.
- Admin reconciliation screens.
- Scheduled reconciliation against Stripe/PayPal APIs.
- Granular payment permissions beyond existing admin access.
- Migration from legacy `paid` status to `succeeded`.
- Migration from full-class polymorphic names to morph aliases.
- Historical transaction reconstruction for payments that completed before the transaction ledger existed.

The `eclise:verify-payment-migration` command should be run before tightening constraints or changing legacy status values.
