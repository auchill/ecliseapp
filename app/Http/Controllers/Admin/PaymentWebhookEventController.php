<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;

class PaymentWebhookEventController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.payments.webhooks', [
            'events' => PaymentWebhookEvent::query()
                ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->string('provider')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
