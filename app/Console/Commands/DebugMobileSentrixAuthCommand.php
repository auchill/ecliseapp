<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixClient;
use App\Services\MobileSentrix\MobileSentrixException;
use Illuminate\Console\Command;

class DebugMobileSentrixAuthCommand extends Command
{
    protected $signature = 'mobilesentrix:debug-auth';

    protected $description = 'Show safe MobileSentrix authentication diagnostics without exposing credentials.';

    public function handle(MobileSentrixClient $client): int
    {
        try {
            $diagnostics = $client->credentialDiagnostics();
        } catch (MobileSentrixException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Environment: '.($diagnostics['environment'] ?: 'Not configured'));
        $this->line('Base URL: '.($diagnostics['base_url'] ?: 'Not configured'));
        $this->line('Consumer Name configured: '.$this->yesNo($diagnostics['consumer_name_configured']));
        $this->line('Consumer Key configured: '.$this->yesNo($diagnostics['consumer_key_configured']));
        $this->line('Consumer Secret configured: '.$this->yesNo($diagnostics['consumer_secret_configured']));
        $this->line('Access Token configured: '.$this->yesNo($diagnostics['access_token_configured']));
        $this->line('Access Token Secret configured: '.$this->yesNo($diagnostics['access_token_secret_configured']));
        $this->line('Active DB settings row ID: '.($diagnostics['active_settings_id'] ?? 'None'));
        $this->line('Last authenticated at: '.($diagnostics['last_authenticated_at'] ?? 'Never'));
        $this->line('Token source: '.$diagnostics['token_source']);
        $this->line('Auth transport: '.$diagnostics['auth_transport']);

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
