<!doctype html>
<html>
    <body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
        <h1 style="color: #0D1321;">Payment confirmed</h1>
        <p>Hello {{ $payment->payable->customer?->full_name ?? 'Customer' }},</p>
        <p>We received your {{ $payment->gatewayLabel() }} payment.</p>
        <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
            <tr><td><strong>Amount</strong></td><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
            <tr><td><strong>Status</strong></td><td>{{ $payment->statusLabel() }}</td></tr>
            <tr><td><strong>Reference</strong></td><td>{{ $payment->gateway_reference_id ?: 'Pending reference' }}</td></tr>
            <tr><td><strong>Paid at</strong></td><td>{{ $payment->paid_at?->format('M j, Y g:i A') }}</td></tr>
        </table>
        <p>Thank you,<br>Eclise Technology Inc.</p>
    </body>
</html>
