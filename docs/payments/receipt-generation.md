# Receipt Generation

Receipts are persisted on successful payment records.

Format:

`RCT-YYYY-0000001`

Rules:

- Receipt numbers are assigned by `ReceiptService` after payment finalization.
- Receipt numbers are based on the saved payment id.
- Duplicate webhook handling can fill a missing receipt without creating another transaction.
- Receipts are available from customer payment pages and admin payment pages only for paid payments.
