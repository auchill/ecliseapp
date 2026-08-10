# Stripe Sandbox Setup

Required environment values:

- `STRIPE_KEY` — publishable key, must start with `pk_test_` in sandbox
- `STRIPE_SECRET` — secret key, must start with `sk_test_` in sandbox
- `STRIPE_WEBHOOK_SECRET` — signing secret, must start with `whsec_`

Optional:

- `STRIPE_API_VERSION` — pinned Stripe API version for outbound calls (default `2024-06-20`)
- `STRIPE_TIMEOUT` / `STRIPE_CONNECT_TIMEOUT` — HTTP timeouts in seconds

Stripe is shown to customers only when all of the following hold: Stripe is enabled in Payment Settings, the three credentials are present **and well-formed**, the publishable and secret keys are the same mode (both test or both live), the payment currency is CAD, the checkout and webhook routes are registered, and — in production — `APP_URL` uses HTTPS.

Readiness reports presence and validity only. Credential values are never displayed, logged, or sent to the browser.

## Local Webhook Delivery

Payments deliberately stay `pending` until a signed webhook confirms them. On a local host such as `http://ecliseapp.test`, Stripe cannot reach the application, so webhook forwarding is required or nothing will ever settle.

Check the actual application URL first (`APP_URL` in `.env` — Herd, Valet and `artisan serve` all differ), then forward to it:

```bash
stripe listen --forward-to http://ecliseapp.test/webhooks/stripe
```

`stripe listen` prints its own signing secret. That secret is **different** from the one shown on a dashboard webhook endpoint. Copy the printed `whsec_…` into `STRIPE_WEBHOOK_SECRET`, then run `php artisan config:clear`. Using the dashboard secret with CLI forwarding produces `400 Invalid Stripe signature` on every event, which is the most common reason a sandbox run appears stuck.

The Stripe CLI is an external dependency and is not installed automatically. If it is unavailable, expose the application through a public tunnel and register that URL as a dashboard webhook endpoint instead, using that endpoint's signing secret.

Useful CLI commands:

```bash
stripe trigger checkout.session.completed
stripe events resend <event_id>
```

## Testing Rules

- Create payments server-side; never accept amounts from the request.
- Use Stripe Checkout for customer payment. Do not add custom card fields.
- Confirm payment state through signed webhooks only.
- Do not mark payments paid from the browser success route.
- Verify webhook `amount_total` and `currency` match the local payment before finalizing.
- Verify the session's `payment_status` before finalizing; a completed session is not necessarily a funded one.
- Use Stripe idempotency keys for Checkout Session and refund creation.
- Never use real card details in sandbox.

## Recommended Sandbox Flow

1. Configure sandbox keys in `.env`.
2. Start `stripe listen` and copy its signing secret into `.env`.
3. Run `php artisan config:clear`.
4. Start a queue worker so receipt and admin emails are delivered.
5. Confirm Admin > Payments > Settings shows Stripe readiness `ready` and mode `Test`.
6. Create a shop or repair payment with Stripe selected.
7. Complete Stripe Checkout with a sandbox test card.
8. Confirm the payment has a receipt, a transaction row, an invoice balance update, and no duplicate transaction when the webhook is replayed.
9. Work through [payment-stage-3-stripe-sandbox-acceptance.md](payment-stage-3-stripe-sandbox-acceptance.md).

## Local Verification

```bash
php artisan test tests/Feature/PaymentStageThreeStripeTest.php
php artisan test tests/Feature/PaymentStageThreeStripeHardeningTest.php
php artisan test tests/Feature/PaymentDomainFoundationTest.php
```

PayPal is intentionally deferred for this stage. Do not require `PAYPAL_WEBHOOK_ID` for Stripe activation.
