<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentAuditLogger;
use App\Services\Payments\StripeWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

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

    /**
     * Retries run the same idempotent processing path as live delivery, so replaying an event
     * that already settled produces no second financial effect.
     */
    public function retry(Request $request, PaymentWebhookEvent $event, StripeWebhookProcessor $processor, PaymentAuditLogger $audit)
    {
        $retryable = [PaymentWebhookEvent::STATUS_FAILED, PaymentWebhookEvent::STATUS_IGNORED];

        if ($event->provider !== 'stripe' || ! in_array($event->status, $retryable, true)) {
            return back()->withErrors(['webhook' => 'Only failed or ignored Stripe webhook events can be retried from this screen.']);
        }

        $previousError = $event->error_message;

        try {
            DB::transaction(function () use ($event, $processor): void {
                $locked = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
                $processor->process($locked, $locked->payload ?? []);
            });
        } catch (Throwable $exception) {
            $audit->log('webhook.retry.failed', $event->fresh(), $request->user(), [
                'webhook_event_id' => $event->id,
                'provider_event_id' => $event->provider_event_id,
                'previous_error' => $previousError,
            ], $request->ip());

            return back()->withErrors(['webhook' => 'Webhook retry failed: '.$exception->getMessage()]);
        }

        $audit->log('webhook.retry.succeeded', $event->fresh(), $request->user(), [
            'webhook_event_id' => $event->id,
            'provider_event_id' => $event->provider_event_id,
            'previous_error' => $previousError,
        ], $request->ip());

        return back()->with('status', 'Webhook event retried.');
    }
}
