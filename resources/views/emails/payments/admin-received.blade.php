<!doctype html>
<html>
    <body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
        <h1 style="color: #0D1321;">New payment received</h1>
        <p>A {{ $payment->gatewayLabel() }} payment has been confirmed.</p>
        <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
            <tr><td><strong>Customer</strong></td><td>{{ $payment->payable->customer_name }}</td></tr>
            <tr><td><strong>Email</strong></td><td>{{ $payment->payable->email }}</td></tr>
            <tr><td><strong>Amount</strong></td><td>{{ strtoupper($payment->currency) }} ${{ number_format($payment->amount, 2) }}</td></tr>
            <tr><td><strong>Status</strong></td><td>{{ $payment->statusLabel() }}</td></tr>
            <tr><td><strong>Reference</strong></td><td>{{ $payment->gateway_reference_id ?: 'Pending reference' }}</td></tr>
        </table>
    </body>
</html>
