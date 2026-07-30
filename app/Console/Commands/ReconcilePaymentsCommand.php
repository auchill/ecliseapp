<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentAuditLogger;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'eclise:reconcile-payments
        {--provider= : Provider to check}
        {--from= : Start date}
        {--to= : End date}
        {--payment= : Payment number}
        {--json : Output JSON}
        {--repair : Reserved for future safe automated corrections}
        {--dry-run : Do not write corrections}';

    protected $description = 'Reconcile local payment, invoice, receipt, refund, and transaction state.';

    public function handle(PaymentReconciliationService $reconciliation, PaymentAuditLogger $audit): int
    {
        $filters = collect([
            'provider' => $this->option('provider'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'payment' => $this->option('payment'),
        ])->filter(fn ($value): bool => filled($value))->all();

        $report = $reconciliation->report($filters);
        $report['dry_run'] = (bool) $this->option('dry-run');
        $report['repair_requested'] = (bool) $this->option('repair');

        if (! $report['dry_run']) {
            $audit->log('reconciliation.run', null, null, [
                'filters' => $filters,
                'issue_count' => $report['issue_count'],
                'dry_run' => $report['dry_run'],
                'repair_requested' => $report['repair_requested'],
            ]);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Payment reconciliation checked at '.$report['checked_at']);
        $this->line('Issues: '.$report['issue_count']);

        foreach ($report['issues'] as $issue) {
            $this->line("- {$issue['payment']} {$issue['code']}: {$issue['message']}");
        }

        if ($this->option('repair')) {
            $this->warn('No automated financial repairs are implemented in this stage.');
        }

        return self::SUCCESS;
    }
}
