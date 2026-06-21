<?php

namespace App\Console\Commands;

use App\Services\MobileSentrix\MobileSentrixAuthService;
use App\Services\MobileSentrix\MobileSentrixException;
use Illuminate\Console\Command;

class AuthenticateMobileSentrixCommand extends Command
{
    protected $signature = 'mobilesentrix:authenticate
        {--oauth-token= : OAuth token returned by the MobileSentrix callback}
        {--oauth-verifier= : OAuth verifier returned by the MobileSentrix callback}
        {--callback-url= : Full callback URL returned by MobileSentrix}';

    protected $description = 'Run the MobileSentrix OAuth authentication flow and store encrypted access tokens.';

    public function handle(MobileSentrixAuthService $auth): int
    {
        $missing = $this->missingConfiguration();

        if ($missing !== []) {
            $this->error('MobileSentrix OAuth configuration is incomplete.');
            $this->line('Missing: '.implode(', ', $missing));

            return self::FAILURE;
        }

        try {
            $callbackCredentials = $this->callbackCredentials();

            if ($callbackCredentials) {
                return $this->exchangeAndReport($auth, $callbackCredentials['oauth_token'], $callbackCredentials['oauth_verifier']);
            }

            $temporaryCredentials = $auth->requestTemporaryCredentials();

            if (! $temporaryCredentials) {
                $this->warn('This MobileSentrix OAuth flow requires browser authorization. Use /admin/parts/mobilesentrix and click Start Live Authentication.');
                $this->line('After MobileSentrix redirects to the callback, the admin page will store the encrypted access tokens.');
                $this->line('For CLI completion, rerun this command with --callback-url= followed by the full callback URL.');

                return self::FAILURE;
            }

            return $this->exchangeAndReport($auth, $temporaryCredentials['oauth_token'], $temporaryCredentials['oauth_verifier']);
        } catch (MobileSentrixException $exception) {
            $this->error($exception->getMessage());
            $this->line('Verify the MobileSentrix .env values, or use the admin "Start Live Authentication" button if browser authorization is required.');

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('MobileSentrix authentication failed before a valid response was received.');

            return self::FAILURE;
        }
    }

    private function exchangeAndReport(MobileSentrixAuthService $auth, string $oauthToken, string $oauthVerifier): int
    {
        $tokens = $auth->exchangeToken($oauthToken, $oauthVerifier);

        $this->info('MobileSentrix authentication completed. Access tokens were stored securely.');
        $this->line('Access Token: '.MobileSentrixAuthService::maskSecret($tokens['access_token'] ?? ''));
        $this->line('Access Token Secret: '.MobileSentrixAuthService::maskSecret($tokens['access_token_secret'] ?? ''));

        return self::SUCCESS;
    }

    private function callbackCredentials(): ?array
    {
        $oauthToken = $this->option('oauth-token');
        $oauthVerifier = $this->option('oauth-verifier');
        $callbackUrl = $this->option('callback-url');

        if (filled($callbackUrl)) {
            parse_str((string) parse_url((string) $callbackUrl, PHP_URL_QUERY), $query);

            $oauthToken = $query['oauth_token'] ?? $oauthToken;
            $oauthVerifier = $query['oauth_verifier'] ?? $oauthVerifier;
        }

        if (blank($oauthToken) && blank($oauthVerifier)) {
            return null;
        }

        if (blank($oauthToken) || blank($oauthVerifier)) {
            throw new MobileSentrixException('Both oauth_token and oauth_verifier are required to complete MobileSentrix authentication.');
        }

        return [
            'oauth_token' => (string) $oauthToken,
            'oauth_verifier' => (string) $oauthVerifier,
        ];
    }

    private function missingConfiguration(): array
    {
        return collect([
            'MOBILESENTRIX_BASE_URL' => config('mobilesentrix.base_url'),
            'MOBILESENTRIX_CONSUMER_NAME' => config('mobilesentrix.consumer_name'),
            'MOBILESENTRIX_CONSUMER_KEY' => config('mobilesentrix.consumer_key'),
            'MOBILESENTRIX_CONSUMER_SECRET' => config('mobilesentrix.consumer_secret'),
            'MOBILESENTRIX_CALLBACK_URL' => config('mobilesentrix.callback_url'),
        ])->filter(fn ($value) => blank($value))->keys()->all();
    }
}
