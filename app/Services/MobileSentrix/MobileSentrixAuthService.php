<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixApiSetting;
use App\Models\MobileSentrixSyncLog;
use Illuminate\Support\Facades\Http;

class MobileSentrixAuthService
{
    public function authorizationUrl(): string
    {
        $credentials = $this->applicationCredentials();

        return $this->url('/oauth/authorize/identifier').'?'.http_build_query([
            'consumer' => $credentials['consumer_name'],
            'authtype' => 1,
            'flowentry' => 'SignIn',
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'callback' => $credentials['callback_url'],
        ]);
    }

    public function exchangeToken(string $oauthToken, string $oauthVerifier): array
    {
        $credentials = $this->applicationCredentials();

        $log = MobileSentrixSyncLog::query()->create([
            'sync_type' => 'authentication',
            'status' => 'started',
            'started_at' => now(),
            'message' => 'Exchanging MobileSentrix OAuth verifier.',
        ]);

        try {
            $response = Http::timeout(config('mobilesentrix.timeout'))
                ->acceptJson()
                ->asJson()
                ->post($this->url('/oauth/authorize/identifiercallback'), [
                    'consumer_key' => $credentials['consumer_key'],
                    'consumer_secret' => $credentials['consumer_secret'],
                    'oauth_token' => $oauthToken,
                    'oauth_verifier' => $oauthVerifier,
                ]);

            if (! $response->successful()) {
                throw new MobileSentrixException('MobileSentrix rejected the OAuth verifier.');
            }

            $payload = $response->json();
            $tokens = data_get($payload, 'data', []);

            if (blank($tokens['access_token'] ?? null) || blank($tokens['access_token_secret'] ?? null)) {
                throw new MobileSentrixException('MobileSentrix returned an invalid access token response.');
            }

            $this->storeTokens($tokens['access_token'], $tokens['access_token_secret'], $credentials);

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'message' => 'MobileSentrix OAuth exchange succeeded. Access tokens were stored securely.',
            ]);

            return $tokens;
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function storeTokens(string $accessToken, string $accessTokenSecret, array $credentials): void
    {
        $settings = MobileSentrixApiSetting::query()
            ->active()
            ->where('environment', $credentials['environment'])
            ->latest('updated_at')
            ->first() ?: new MobileSentrixApiSetting;

        $settings->fill([
            'environment' => $credentials['environment'],
            'base_url' => $credentials['base_url'],
            'consumer_name' => $credentials['consumer_name'],
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'access_token' => $accessToken,
            'access_token_secret' => $accessTokenSecret,
            'callback_url' => $credentials['callback_url'],
            'is_active' => true,
            'last_authenticated_at' => now(),
        ])->save();
    }

    private function applicationCredentials(): array
    {
        $credentials = [
            'environment' => config('mobilesentrix.env'),
            'base_url' => rtrim((string) config('mobilesentrix.base_url'), '/'),
            'consumer_name' => config('mobilesentrix.consumer_name'),
            'consumer_key' => config('mobilesentrix.consumer_key'),
            'consumer_secret' => config('mobilesentrix.consumer_secret'),
            'callback_url' => config('mobilesentrix.callback_url'),
        ];

        $missing = collect($credentials)
            ->except(['environment'])
            ->filter(fn ($value) => blank($value))
            ->keys();

        if ($missing->isNotEmpty()) {
            throw new MobileSentrixException('MobileSentrix OAuth is missing required application credentials.');
        }

        return $credentials;
    }

    private function url(string $path): string
    {
        return rtrim(config('mobilesentrix.base_url'), '/').'/'.ltrim($path, '/');
    }
}
