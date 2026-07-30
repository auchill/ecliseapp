# PayPal Sandbox Setup

Required environment values:

- `PAYPAL_CLIENT_ID`
- `PAYPAL_SECRET`
- `PAYPAL_MODE=sandbox`
- `PAYPAL_WEBHOOK_ID`

Testing rules:

- Create PayPal orders server-side.
- Browser return may capture the order but must not mark the local payment paid.
- Signed webhook confirmation is the authority for local finalization.
