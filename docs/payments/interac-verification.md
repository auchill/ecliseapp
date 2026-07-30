# Interac Verification

Customer Interac payments are created with `status = pending_verification`.

Admin verification flow:

1. Open Admin > Payments > Pending Verification.
2. Review customer, invoice, amount, notes, and proof details where available.
3. Verify the payment only after confirming the bank deposit.
4. `ManualPaymentService::verifyInterac()` finalizes the payment.

Verification writes:

- `verified_by`
- `verified_at`
- `received_by`
- `paid_at`
- receipt number
- manual confirmation transaction
- invoice balance update
- payment audit log

Rejecting an Interac payment sets the payment to `failed`, records rejection reason, and writes a manual rejection transaction.
