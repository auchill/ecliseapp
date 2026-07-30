# Shop Checkout Payment Workflow

Shop checkout remains cart-snapshot based.

Flow:

1. Customer checkout validates cart items and fulfillment.
2. Checkout creates a `shop_order` invoice against the cart.
3. Checkout creates a pending payment with checkout snapshot data.
4. Gateway redirects or manual instructions are shown.
5. Webhook or admin verification finalizes payment.
6. Finalization creates the order, order items, status update, receipt, invoice association, and deletes the cart.

Browser return routes do not mark Stripe or PayPal payments paid. Webhooks remain the authority.
