<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PaymentAuditLogger
{
    public function log(
        string $event,
        ?Model $auditable = null,
        ?User $actor = null,
        array $metadata = [],
        ?string $sourceIp = null,
    ): void {
        if (! Schema::hasTable('payment_audit_logs')) {
            return;
        }

        PaymentAuditLog::query()->create([
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'payment_id' => $auditable instanceof Payment ? $auditable->id : ($metadata['payment_id'] ?? null),
            'invoice_id' => $auditable instanceof Invoice ? $auditable->id : ($metadata['invoice_id'] ?? null),
            'refund_id' => $auditable instanceof PaymentRefund ? $auditable->id : ($metadata['refund_id'] ?? null),
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : null,
            'source_ip' => $sourceIp,
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        return app(PaymentPayloadSanitizer::class)->sanitize($metadata);
    }
}
