# Invoice Generation

Invoices are local snapshots. They are not generated in Blade views and they do not call payment gateways.

Shop checkout:

- `CheckoutController` creates a cart-backed `shop_order` invoice before payment handoff.
- When the payment is finalized, the invoice is attached to the generated order.

Shop order:

- `PaymentFinalizer` creates or associates an order-backed `shop_order` invoice when existing orders are paid.

Repair:

- Accepted repair proposals create `repair_deposit` invoices according to payment settings.
- Repair completion creates `repair_final` invoices for the remaining balance.
- Additional repair charges use `repair_additional_charge`.

Invoice totals and line items are refreshed only while the invoice has no paid amount.
