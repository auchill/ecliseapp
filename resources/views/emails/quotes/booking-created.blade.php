<p>Hello {{ $booking->customer_name }},</p>
<p>Your repair booking has been created from your approved quote.</p>
<p><strong>Tracking number:</strong> {{ $booking->tracking_number }}</p>
<p>Sign in to your Eclise account, open Repair &gt; Book Repair, and enter this tracking number to choose pickup or shipping and complete payment.</p>
<p><strong>Device:</strong> {{ $booking->deviceLabel() }}<br>
<strong>Total:</strong> ${{ number_format($booking->total_amount ?: $booking->repair_total, 2) }}</p>
