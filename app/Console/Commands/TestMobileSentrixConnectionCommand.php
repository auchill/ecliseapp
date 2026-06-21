<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixClient;
use App\Services\MobileSentrix\MobileSentrixException;
use Illuminate\Console\Command;

class TestMobileSentrixConnectionCommand extends Command
{
    protected $signature = 'mobilesentrix:test-connection';

    protected $description = 'Test the live MobileSentrix API connection using stored encrypted OAuth tokens.';

    public function handle(MobileSentrixClient $client): int
    {
        $missing = $client->missingCredentialNames();

        if (in_array('access_token', $missing, true) || in_array('access_token_secret', $missing, true)) {
            $this->error('MobileSentrix is not authenticated yet. Run php artisan mobilesentrix:authenticate or use the admin Authenticate Server-Side button.');

            return self::FAILURE;
        }

        if ($missing !== []) {
            $this->error('MobileSentrix API configuration is incomplete.');
            $this->line('Missing: '.implode(', ', $missing));

            return self::FAILURE;
        }

        try {
            $result = $client->testConnection();

            $this->info($result['message']);
            $this->line('Category response count: '.($result['sample_count'] ?? 0));

            return self::SUCCESS;
        } catch (MobileSentrixException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('MobileSentrix API connection failed. Please verify credentials and authenticate again.');

            return self::FAILURE;
        }
    }
}
