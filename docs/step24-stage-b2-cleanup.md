# Step 24 Stage B.2 Cleanup

This cleanup removes legacy quote, repair, order, and payment compatibility columns after the Stage B.1 verifier reports:

- READY FOR STAGE B CLEANUP
- active_runtime_blockers = 0
- safe_for_cleanup = yes

## Production Backup Requirement

A verified production database backup is required immediately before running the Stage B.2 cleanup migrations.

The backup must be restorable and should include all application tables, especially:

- quotes
- repairs
- repair_shipping
- orders
- order_shipping
- payments
- order_items
- repair_status_updates
- order_status_updates

Do not store production database credentials or production backup files in source control.

## Local Pre-Cleanup Record

For the local development database used during implementation, the pre-cleanup verifier reported `READY FOR STAGE B CLEANUP`, `active_runtime_blockers = 0`, and `safe_for_cleanup = yes`.

Affected local row counts before cleanup:

- quotes: 0
- repairs: 1
- repair_shipping: 0
- orders: 0
- order_shipping: 0
- payments: 0
- order_items: 0
- repair_status_updates: 2
- order_status_updates: 0

The one informational repair tracking mismatch was an obsolete internal repair reference:

- repair_number: `ECL-REP-2026-0000001`
- legacy tracking_number: `ECL-REP-2026-0001`
- delivery_tracking_number: null

No carrier tracking value needed to be preserved before dropping the legacy repair tracking column.

## Rollback Limitation

Rolling back the Stage B.2 cleanup migrations recreates schema columns only. It does not restore the removed historical legacy values.

Restore the pre-cleanup database backup when full data rollback is required.
