# Repair Deposit Workflow

Repair deposit amount is controlled by `payment_settings.repair_deposit_type`.

Supported deposit types:

- `none`
- `fixed`
- `percentage`
- `minimum`
- `full_payment`

Current default is `full_payment` to preserve the existing repair payment behavior.

When a customer accepts a repair proposal, `RepairNegotiationService` creates the payment from a repair invoice. Interac waits for admin verification. Stripe and PayPal wait for webhook confirmation.
