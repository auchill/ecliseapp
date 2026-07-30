# Stage 2 Deployment

Recommended order:

1. Back up database.
2. Deploy code.
3. Run migrations.
4. Clear config and app cache.
5. Run payment migration verification.
6. Run reconciliation dry run.
7. Confirm admin payment pages load.
8. Test one sandbox Stripe and PayPal payment.
9. Test one manual payment and one Interac verification.

Commands:

```bash
php artisan migrate
php artisan config:clear
php artisan cache:clear
php artisan eclise:verify-payment-migration --json
php artisan eclise:reconcile-payments --dry-run --json
```
