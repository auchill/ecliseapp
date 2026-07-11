<p>A new quote request was submitted.</p>
<p><strong>Quote:</strong> #{{ $quote->id }}</p>
<p><strong>Customer:</strong> {{ $quote->customer?->full_name ?? 'Customer unavailable' }}<br>
<strong>Email:</strong> {{ $quote->customer?->email ?? 'Unavailable' }}<br>
<strong>Phone:</strong> {{ $quote->customer?->phone ?? 'Unavailable' }}</p>
<p><strong>Device:</strong> {{ $quote->deviceLabel() }}<br>
<strong>Issue:</strong> {{ $quote->issueCategory?->name }}</p>
<p>{{ $quote->issue_description }}</p>
