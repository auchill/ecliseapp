# Manual Payments

Manual payments are recorded from the admin payment module against an open invoice.

Supported methods are controlled by `payment_settings`: `cash`, `debit_terminal`, `credit_terminal`, `pay_in_store`, and `interac`.

Operational flow:

1. Open Admin > Payments > Manual Payment.
2. Select an invoice with a positive balance.
3. Enter amount, currency, method, optional reference, notes, and proof file.
4. Non-Interac methods are finalized immediately through `ManualPaymentService`.
5. The finalizer writes the payment, transaction ledger row, receipt number, invoice balance sync, and audit log.

Controls:

- Amount cannot exceed invoice balance.
- Currency must match the invoice currency.
- Duplicate active manual references are rejected.
- Proof files are stored on the private local disk under `payment-proofs`.
- Cart-backed shop checkout invoices can be completed when the pending checkout payment has a valid checkout snapshot.
