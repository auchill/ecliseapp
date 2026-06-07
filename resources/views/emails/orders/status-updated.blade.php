{{-- Configure SMTP in .env with MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME when real sending is ready. --}}
<!doctype html>
<html>
    <body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
        <h1 style="color: #0D1321;">Order status update</h1>
        <p>Hello {{ $order->customer_name }},</p>
        <p>Your order <strong>{{ $order->order_number }}</strong> is now <strong>{{ $order->status }}</strong>.</p>

        <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
            <tr><td><strong>Fulfillment</strong></td><td>{{ $order->fulfillmentLabel() }}</td></tr>
            <tr><td><strong>Payment status</strong></td><td>{{ $order->payment_status }}</td></tr>
            @if ($order->isShipping())
                <tr><td><strong>Shipping method</strong></td><td>{{ $order->shipping_method_name ?: 'To be confirmed' }}</td></tr>
                <tr><td><strong>Estimated delivery</strong></td><td>{{ $order->shipping_delivery_days ?: 'To be confirmed' }}</td></tr>
                <tr><td><strong>Shipping base</strong></td><td>${{ number_format($order->shipping_base_cost, 2) }}</td></tr>
                <tr><td><strong>Shipping discount</strong></td><td>${{ number_format($order->shipping_discount_amount, 2) }}</td></tr>
                <tr><td><strong>Final shipping</strong></td><td>${{ number_format($order->shipping_cost, 2) }}</td></tr>
            @else
                <tr><td><strong>Shipping</strong></td><td>No charge for store pickup.</td></tr>
            @endif
            <tr><td><strong>Total</strong></td><td>${{ number_format($order->total, 2) }}</td></tr>
            @if ($order->delivery_carrier)
                <tr><td><strong>Delivery carrier</strong></td><td>{{ $order->delivery_carrier }}</td></tr>
            @endif
            @if ($order->tracking_number)
                <tr><td><strong>Tracking number</strong></td><td>{{ $order->tracking_number }}</td></tr>
            @endif
        </table>

        @if ($order->isShipping())
            <h2 style="color: #0D1321;">Shipping address</h2>
            @foreach ($order->shippingAddressLines() as $line)
                <div>{{ $line }}</div>
            @endforeach
        @else
            <p><strong>Pickup instructions:</strong> Your order will be prepared for store pickup. You will receive an email when it is ready.</p>
        @endif

        @if ($statusUpdate?->note)
            <p><strong>Note:</strong> {{ $statusUpdate->note }}</p>
        @elseif ($order->customer_notes)
            <p><strong>Note:</strong> {{ $order->customer_notes }}</p>
        @endif

        <p>Track your order at {{ route('orders.track') }} using your order number and email or phone number.</p>
        <p>Thank you,<br>Eclise Technology Inc.</p>
    </body>
</html>
