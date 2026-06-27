<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixApiSetting;
use App\Models\MobileSentrixSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileSentrixAuthService
{
    private const BLOCKED_OAUTH_MESSAGE = 'MobileSentrix rejected or blocked the OAuth request with HTTP 403. This may be caused by Cloudflare, IP restriction, environment mismatch, unregistered callback URL, invalid credentials, or account not enabled for the Canada preprod API. Contact MobileSentrix support with the Cloudflare Ray ID from the block page.';

    public function authorizationUrl(): string
    {
        $credentials = $this->applicationCredentials();

        return $this->url('/oauth/authorize/identifier').'?'.$this->identifierQueryString($credentials);
    }

    public function requestTemporaryCredentials(): ?array
    {
        $credentials = $this->applicationCredentials();

        try {
            $response = Http::timeout(config('mobilesentrix.timeout'))
                ->connectTimeout(config('mobilesentrix.connect_timeout'))
                ->acceptJson()
                ->get($this->url('/oauth/authorize/identifier').'?'.$this->identifierQueryString($credentials));
        } catch (\Throwable $exception) {
            Log::warning('MobileSentrix OAuth temporary token request failed before a response.', [
                'exception' => $exception::class,
                'oauth_context' => $this->safeOAuthContext($credentials),
            ]);

            throw new MobileSentrixException('MobileSentrix OAuth authentication could not be started.');
        }

        if ($response->successful()) {
            $payload = $response->json();

            if (! is_array($payload)) {
                return null;
            }

            $oauthToken = data_get($payload, 'oauth_token') ?: data_get($payload, 'data.oauth_token');
            $oauthVerifier = data_get($payload, 'oauth_verifier') ?: data_get($payload, 'data.oauth_verifier');

            if (filled($oauthToken) && filled($oauthVerifier)) {
                return [
                    'oauth_token' => $oauthToken,
                    'oauth_verifier' => $oauthVerifier,
                ];
            }

            return null;
        }

        $location = $response->header('Location');

        if (filled($location)) {
            parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

            if (filled($query['oauth_token'] ?? null) && filled($query['oauth_verifier'] ?? null)) {
                return [
                    'oauth_token' => $query['oauth_token'],
                    'oauth_verifier' => $query['oauth_verifier'],
                ];
            }
        }

        if ($response->status() >= 300 && $response->status() < 400) {
            return null;
        }

        if ($response->status() === 403) {
            Log::warning('MobileSentrix OAuth temporary token request blocked.', [
                'status' => $response->status(),
                'oauth_context' => $this->safeOAuthContext($credentials),
            ]);

            throw new MobileSentrixException(self::BLOCKED_OAUTH_MESSAGE, 403);
        }

        Log::warning('MobileSentrix OAuth temporary token request failed.', [
            'status' => $response->status(),
            'oauth_context' => $this->safeOAuthContext($credentials),
        ]);

        throw new MobileSentrixException('MobileSentrix OAuth authentication could not be started.', $response->status());
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
                ->connectTimeout(config('mobilesentrix.connect_timeout'))
                ->acceptJson()
                ->asJson()
                ->post($this->url('/oauth/authorize/identifiercallback'), [
                    'consumer_key' => $credentials['consumer_key'],
                    'consumer_secret' => $credentials['consumer_secret'],
                    'oauth_token' => $oauthToken,
                    'oauth_verifier' => $oauthVerifier,
                ]);

            if (! $response->successful()) {
                Log::warning('MobileSentrix OAuth token exchange failed.', [
                    'status' => $response->status(),
                ]);

                if ($response->status() === 403) {
                    throw new MobileSentrixException(self::BLOCKED_OAUTH_MESSAGE, 403);
                }

                throw new MobileSentrixException('MobileSentrix rejected the OAuth verifier.');
            }

            $payload = $response->json();
            $tokens = data_get($payload, 'data', $payload);

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
                'message' => $this->safeMessage($exception),
            ]);

            throw $exception;
        }
    }

    private function storeTokens(string $accessToken, string $accessTokenSecret, array $credentials): void
    {
        DB::transaction(function () use ($accessToken, $accessTokenSecret, $credentials): void {
            $settings = MobileSentrixApiSetting::query()
                ->where('environment', $credentials['environment'])
                ->where('base_url', $credentials['base_url'])
                ->latest('updated_at')
                ->latest('id')
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

            MobileSentrixApiSetting::query()
                ->where('environment', $credentials['environment'])
                ->whereKeyNot($settings->getKey())
                ->update(['is_active' => false]);
        });
    }

    public static function maskSecret(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return 'missing';
        }

        if (mb_strlen($value) <= 8) {
            return mb_substr($value, 0, 2).'****'.mb_substr($value, -2);
        }

        return mb_substr($value, 0, 4).'********'.mb_substr($value, -4);
    }

    private function identifierQueryString(array $credentials): string
    {
        return http_build_query($this->identifierQueryParams($credentials), '', '&', PHP_QUERY_RFC3986);
    }

    private function identifierQueryParams(array $credentials): array
    {
        return [
            'consumer' => $credentials['consumer_name'],
            'authtype' => 1,
            'flowentry' => 'SignIn',
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'callback' => $credentials['callback_url'],
        ];
    }

    private function safeOAuthContext(array $credentials): array
    {
        return [
            'environment' => $credentials['environment'] ?? null,
            'base_url' => $credentials['base_url'] ?? null,
            'callback_url' => $credentials['callback_url'] ?? null,
            'consumer_name_configured' => filled($credentials['consumer_name'] ?? null),
            'consumer_key_configured' => filled($credentials['consumer_key'] ?? null),
            'consumer_secret_configured' => filled($credentials['consumer_secret'] ?? null),
        ];
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
        return rtrim((string) config('mobilesentrix.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function safeMessage(\Throwable $exception): string
    {
        if ($exception instanceof MobileSentrixException) {
            return Str::limit($exception->getMessage(), 180, '');
        }

        return 'MobileSentrix OAuth exchange failed before a valid response was received.';
    }
}
