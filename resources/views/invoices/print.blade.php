<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>{{ $invoice->invoice_number }}</title>
        <link rel="stylesheet" href="{{ asset('build/assets/app-DmHed3W8.css') }}">
        <style>body{padding:32px}.no-print{margin-bottom:20px}@media print{.no-print{display:none}}</style>
    </head>
    <body>
        <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
        @include('invoices.partials.invoice-panel', ['invoice' => $invoice])
    </body>
</html>
