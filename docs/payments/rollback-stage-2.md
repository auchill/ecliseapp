# Stage 2 Rollback

Rollback should be avoided after live payments are recorded because Stage 2 adds operational ledgers.

If rollback is required before using the new flows:

1. Disable new payment methods in admin settings.
2. Stop accepting new payments.
3. Export payment, invoice, refund, and audit rows.
4. Restore the database backup taken before migration.
5. Revert code deployment.

Do not drop payment tables in production after payments, refunds, receipts, or invoices have been created.
