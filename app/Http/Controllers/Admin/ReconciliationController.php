<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentAuditLogger;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index(Request $request, PaymentReconciliationService $reconciliation)
    {
        $report = $request->hasAny(['provider', 'payment', 'from', 'to'])
            ? $reconciliation->report($request->only(['provider', 'payment', 'from', 'to']))
            : null;

        return view('admin.payments.reconciliation', ['report' => $report]);
    }

    public function run(Request $request, PaymentReconciliationService $reconciliation, PaymentAuditLogger $audit)
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:80'],
            'payment' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $report = $reconciliation->report($data);
        $audit->log('reconciliation.run', null, $request->user(), ['issue_count' => $report['issue_count']], $request->ip());

        return view('admin.payments.reconciliation', ['report' => $report]);
    }
}
