<p>Hello {{ $quote->customer?->full_name ?? 'Customer' }},</p>
<p>We received your quote request <strong>#{{ $quote->id }}</strong>.</p>
<p>Eclise will review the device details and contact you by email or phone to confirm pricing and next steps.</p>
<p><strong>Device:</strong> {{ $quote->deviceLabel() }}<br>
<strong>Issue:</strong> {{ $quote->issueCategory?->name }}</p>
