<p>A new quote request was submitted.</p>
<p><strong>Quote:</strong> #{{ $quote->id }}</p>
<p><strong>Customer:</strong> {{ $quote->customer_name }}<br>
<strong>Email:</strong> {{ $quote->email }}<br>
<strong>Phone:</strong> {{ $quote->phone_number }}</p>
<p><strong>Device:</strong> {{ $quote->deviceLabel() }}<br>
<strong>Issue:</strong> {{ $quote->issueCategory?->name }}</p>
<p>{{ $quote->issue_description }}</p>
