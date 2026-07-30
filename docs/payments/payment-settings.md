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
