{{-- Configure SMTP in .env with MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME when real sending is ready. --}}
<!doctype html>
<html>
    <body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
        <h1 style="color: #0D1321;">Repair status update</h1>
        <p>Hello {{ $repair->customer?->full_name ?? 'Customer' }},</p>
        <p>Your repair <strong>{{ $repair->repair_number }}</strong> for {{ $repair->deviceLabel() }} is now <strong>{{ $repair->statusLabel() }}</strong>.</p>

        <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
            <tr><td><strong>Device type</strong></td><td>{{ $repair->deviceTypeName() }}</td></tr>
            <tr><td><strong>Fulfillment</strong></td><td>{{ $repair->fulfillmentLabel() }}</td></tr>
            @if ($repair->isShipping())
                <tr><td><strong>Shipping method</strong></td><td>{{ $repair->shipping_method_name ?: 'To be confirmed' }}</td></tr>
                <tr><td><strong>Estimated delivery</strong></td><td>{{ $repair->shipping_delivery_days ?: 'To be confirmed' }}</td></tr>
                <tr><td><strong>Shipping base</strong></td><td>${{ number_format($repair->shipping_base_cost, 2) }}</td></tr>
                <tr><td><strong>Shipping discount</strong></td><td>${{ number_format($repair->shipping_discount_amount, 2) }}</td></tr>
                <tr><td><strong>Final shipping</strong></td><td>${{ number_format($repair->shipping_cost, 2) }}</td></tr>
            @else
                <tr><td><strong>Shipping</strong></td><td>No charge for store pickup.</td></tr>
            @endif
            <tr><td><strong>Repair total</strong></td><td>${{ number_format($repair->repair_total, 2) }}</td></tr>
            @if ($repair->estimated_completion_date)
                <tr><td><strong>Estimated completion</strong></td><td>{{ $repair->estimated_completion_date->format('M j, Y') }}</td></tr>
            @endif
            @if ($repair->delivery_carrier)
                <tr><td><strong>Delivery carrier</strong></td><td>{{ $repair->delivery_carrier }}</td></tr>
            @endif
            @if ($repair->delivery_tracking_number)
                <tr><td><strong>Carrier tracking number</strong></td><td>{{ $repair->delivery_tracking_number }}</td></tr>
            @endif
        </table>

        @if ($repair->isShipping())
            <h2 style="color: #0D1321;">Shipping address</h2>
            @forelse ($repair->shippingAddressLines() as $line)
                <div>{{ $line }}</div>
            @empty
                <div>Shipping address unavailable.</div>
            @endforelse
        @else
            <p><strong>Pickup instructions:</strong> We will notify you when your repaired device is ready for pickup.</p>
        @endif

        @if ($statusUpdate?->note)
            <p><strong>Note:</strong> {{ $statusUpdate->note }}</p>
        @elseif ($repair->customer_notes)
            <p><strong>Note:</strong> {{ $repair->customer_notes }}</p>
        @endif

        <p>Track your repair at {{ route('repairs.track') }} using your repair number.</p>
        <p>Thank you,<br>Eclise Technology Inc.</p>
    </body>
</html>
