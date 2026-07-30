# Stripe Sandbox Setup

Required environment values:

- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`

Testing rules:

- Create payments server-side.
- Use Stripe Checkout for customer payment.
- Confirm payment state through signed webhooks.
- Do not mark payments paid from the browser success route.

Local verification:

```bash
php artisan test tests/Feature/PaymentDomainFoundationTest.php
```
