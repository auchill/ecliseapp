# Repair Final Balance Workflow

Repair final balances use `InvoiceService::createRepairFinalInvoice()`.

The invoice total is calculated from the repair total minus confirmed successful repair payments.

Admin status gates can require:

- a deposit before repair work starts
- full payment before pickup
- full payment before delivery

These gates inspect confirmed payment state. They do not trust a submitted `payment_status` value as proof of payment.
