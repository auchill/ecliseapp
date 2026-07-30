# Refund Workflow

Refunds use `payment_refunds` and do not delete payments.

Flow:

1. Admin requests a refund from a paid payment.
2. A second admin approves if approval is required.
3. Admin processes the refund manually.
4. Payment refunded amount and status are updated.
5. A refund transaction row is written.
6. The invoice balance is resynchronized.

Refund statuses include `pending`, `approved`, `processing`, `succeeded`, and failure states from the `RefundStatus` enum.
