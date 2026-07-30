<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentAuditLogger;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentSettingsController extends Controller
{
    public function edit(PaymentSettingsService $settings)
    {
        return view('admin.payments.settings', ['settings' => $settings->all()]);
    }

    public function update(Request $request, PaymentSettingsService $settings, PaymentAuditLogger $audit)
    {
        $data = $request->all();

        foreach (array_keys(PaymentSettingsService::DEFAULTS) as $key) {
            if (is_bool(PaymentSettingsService::DEFAULTS[$key])) {
                $data[$key] = $request->boolean($key);
            }
        }

        try {
            $settings->update($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['settings' => $exception->getMessage()])->withInput();
        }

        $audit->log('payment.settings.updated', null, $request->user(), ['changed_keys' => array_keys($data)], $request->ip());

        return redirect()->route('admin.payments.settings')->with('status', 'Payment settings updated.');
    }
}
