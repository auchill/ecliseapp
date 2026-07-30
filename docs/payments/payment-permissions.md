# Payment Permissions

Stage 2 seeds payment permission names into the existing `permissions` table.

Examples:

- `payments.view`
- `payments.record.manual`
- `payments.verify.interac`
- `payments.refund.request`
- `payments.refund.approve`
- `payments.reconcile`
- `payments.settings.manage`
- `invoices.view`
- `receipts.print`

Current admin routes remain protected by existing admin middleware. Fine-grained permission enforcement can be tightened after user-permission assignment UI is expanded for operational roles.
