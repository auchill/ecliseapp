# Payment Settings

Payment settings are stored in `payment_settings`.

The admin settings page controls:

- enabled payment methods
- repair deposit policy
- payment gates
- refund approval behavior
- invoice due days and terms
- Interac recipient instructions

Defaults are seeded safely in the Stage 2 migration and are also mirrored in `PaymentSettingsService::DEFAULTS`.

Stripe readiness is shown on the settings page without exposing secret values. Customer checkout hides Stripe unless:

- Stripe is enabled in the settings table.
- `STRIPE_KEY` is a well-formed publishable key (`pk_test_…` or `pk_live_…`).
- `STRIPE_SECRET` is a well-formed secret or restricted key (`sk_…` / `rk_…`).
- `STRIPE_WEBHOOK_SECRET` starts with `whsec_`.
- The publishable and secret keys are the same mode — both test or both live.
- The configured payment currency is CAD.
- The `payments.stripe.success`, `payments.cancel` and `webhooks.stripe` routes are registered, and the webhook route accepts POST.
- In production, `APP_URL` uses HTTPS.

Format validation matters: a key that is merely non-empty can still be unusable, and reporting `ready` on an invalid key hides the problem until checkout fails.

Admins see which requirement is missing, whether credentials are Configured or Missing, and the credential mode (Test / Live). The UI must never print the actual Stripe key, secret, or webhook secret. Stripe credentials live in environment variables only and are never stored in `payment_settings`.

PayPal is reported separately as `deferred`. It is never counted as a Stripe readiness failure and is hidden from customer-facing payment options until a future stage activates it.
