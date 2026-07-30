# Testing And Security

Implemented checks:

- server-created Stripe and PayPal payment sessions
- webhook replay protection
- no browser-return paid finalization for gateways
- manual payment amount and currency validation
- Interac verification and rejection
- receipt persistence
- refund processing
- customer invoice authorization

Recommended commands:

```bash
php artisan test
php artisan route:list
./vendor/bin/pint --test
npm run build
```
