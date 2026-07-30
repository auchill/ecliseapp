# Payment Reconciliation

Run local reconciliation with:

```bash
php artisan eclise:reconcile-payments --dry-run
```

Useful filters:

```bash
php artisan eclise:reconcile-payments --provider=stripe --from=2026-07-01 --to=2026-07-31
php artisan eclise:reconcile-payments --payment=PAY-2026-0000001 --json
```

This stage reports local inconsistencies only. It does not perform automated financial corrections.
