<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixApiSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileSentrixClient
{
    public const AUTH_TRANSPORT_OAUTH_HEADER = 'oauth_header';

    public const AUTH_TRANSPORT_QUERY_PARAMS = 'query_params';

    public const HTTP_401_MESSAGE = 'MobileSentrix returned HTTP 401. Authentication tokens exist, but the API request authorization was rejected. Check auth transport, OAuth signature format, active token row, environment, and credential rotation status.';

    public function isConfigured(): bool
    {
        return collect($this->missingCredentialNames())->isEmpty();
    }

    public function missingCredentialNames(): array
    {
        $credentials = $this->credentials();

        return collect([
            'base_url' => $credentials['base_url'],
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'access_token' => $credentials['access_token'],
            'access_token_secret' => $credentials['access_token_secret'],
        ])->filter(fn ($value) => blank($value))->keys()->all();
    }

    public static function allowedAuthTransports(): array
    {
        return [
            self::AUTH_TRANSPORT_OAUTH_HEADER,
            self::AUTH_TRANSPORT_QUERY_PARAMS,
        ];
    }

    public function testConnection(?string $authTransport = null): array
    {
        $categories = $this->categories($authTransport);

        return [
            'ok' => true,
            'message' => 'MobileSentrix API connection successful.',
            'sample_count' => is_countable($categories) ? count($categories) : 0,
        ];
    }

    public function categories(?string $authTransport = null): array
    {
        return $this->get('/api/rest/categories', [], $authTransport);
    }

    public function category(string|int $id, ?string $authTransport = null): array
    {
        return $this->get('/api/rest/categories/'.$id, [], $authTransport);
    }

    public function products(array $query = [], ?string $authTransport = null): array
    {
        return $this->get('/api/rest/products', $query, $authTransport);
    }

    public function product(string|int $id, array $query = [], ?string $authTransport = null): array
    {
        return $this->get('/api/rest/products/'.$id, $query, $authTransport);
    }

    public function searchProducts(string $query, array $params = [], ?string $authTransport = null): array
    {
        return $this->get('/api/rest/searchproduct', array_merge($params, ['q' => $query]), $authTransport);
    }

    public function lookupBySku(string $sku, bool $filterByBothSku = true, bool $includeDisabled = true): array
    {
        $query = [
            'filter[1][attribute]' => 'sku',
            'filter[1][in][0]' => $sku,
        ];

        if ($filterByBothSku) {
            $query['filter_by_both_sku'] = 'true';
        }

        if ($includeDisabled) {
            $query['disableProducts'] = 'true';
        }

        return $this->products($query);
    }

    public function redactedConfigStatus(): array
    {
        $credentials = $this->credentials();

        return [
            'environment' => $credentials['environment'],
            'base_url' => $credentials['base_url'],
            'consumer_name' => filled($credentials['consumer_name']),
            'consumer_key' => filled($credentials['consumer_key']),
            'consumer_secret' => filled($credentials['consumer_secret']),
            'access_token' => filled($credentials['access_token']),
            'access_token_secret' => filled($credentials['access_token_secret']),
            'stored_access_tokens' => $credentials['token_source'] === 'database'
                && filled($credentials['access_token'])
                && filled($credentials['access_token_secret']),
            'callback_url' => $credentials['callback_url'],
            'sync_enabled' => (bool) config('mobilesentrix.sync_enabled'),
            'last_authenticated_at' => $credentials['settings_last_authenticated_at'],
            'active_settings_id' => $credentials['settings_id'],
            'token_source' => $credentials['token_source'],
            'auth_transport' => $this->configuredAuthTransport(),
        ];
    }

    public function credentialDiagnostics(?string $authTransport = null): array
    {
        $credentials = $this->credentials();

        return [
            'environment' => $credentials['environment'],
            'base_url' => $credentials['base_url'],
            'consumer_name_configured' => filled($credentials['consumer_name']),
            'consumer_key_configured' => filled($credentials['consumer_key']),
            'consumer_secret_configured' => filled($credentials['consumer_secret']),
            'access_token_configured' => filled($credentials['access_token']),
            'access_token_secret_configured' => filled($credentials['access_token_secret']),
            'active_settings_id' => $credentials['settings_id'],
            'last_authenticated_at' => $credentials['settings_last_authenticated_at'],
            'token_source' => $credentials['token_source'],
            'auth_transport' => $this->normalizeAuthTransport($authTransport),
        ];
    }

    public function credentials(): array
    {
        $settings = $this->activeSettings();
        $fromDatabase = $settings instanceof MobileSentrixApiSetting;

        if ($fromDatabase) {
            return [
                'environment' => $settings->environment,
                'base_url' => rtrim((string) $settings->base_url, '/'),
                'consumer_name' => $settings->consumer_name,
                'consumer_key' => $settings->consumer_key,
                'consumer_secret' => $settings->consumer_secret,
                'access_token' => $settings->access_token,
                'access_token_secret' => $settings->access_token_secret,
                'callback_url' => $settings->callback_url,
                'settings_id' => $settings->id,
                'settings_last_authenticated_at' => $settings->last_authenticated_at,
                'token_source' => 'database',
            ];
        }

        return [
            'environment' => config('mobilesentrix.env'),
            'base_url' => rtrim((string) config('mobilesentrix.base_url'), '/'),
            'consumer_name' => config('mobilesentrix.consumer_name'),
            'consumer_key' => config('mobilesentrix.consumer_key'),
            'consumer_secret' => config('mobilesentrix.consumer_secret'),
            'access_token' => config('mobilesentrix.access_token'),
            'access_token_secret' => config('mobilesentrix.access_token_secret'),
            'callback_url' => config('mobilesentrix.callback_url'),
            'settings_id' => null,
            'settings_last_authenticated_at' => null,
            'token_source' => 'env',
        ];
    }

    public function activeSettings(): ?MobileSentrixApiSetting
    {
        $environment = config('mobilesentrix.env');
        $baseUrl = rtrim((string) config('mobilesentrix.base_url'), '/');

        if (blank($environment) || blank($baseUrl)) {
            return null;
        }

        return MobileSentrixApiSetting::query()
            ->active()
            ->where('environment', $environment)
            ->where('base_url', $baseUrl)
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function configuredAuthTransport(): string
    {
        return $this->normalizeAuthTransport(config('mobilesentrix.auth_transport', self::AUTH_TRANSPORT_OAUTH_HEADER));
    }

    public function normalizeAuthTransport(?string $authTransport = null): string
    {
        $authTransport = filled($authTransport)
            ? (string) $authTransport
            : (string) config('mobilesentrix.auth_transport', self::AUTH_TRANSPORT_OAUTH_HEADER);

        if (in_array($authTransport, self::allowedAuthTransports(), true)) {
            return $authTransport;
        }

        throw new MobileSentrixException('Invalid MobileSentrix auth transport. Allowed values: '.implode(', ', self::allowedAuthTransports()).'.');
    }

    private function get(string $path, array $query = [], ?string $authTransport = null): array
    {
        $credentials = $this->credentials();
        $transport = $this->normalizeAuthTransport($authTransport);

        $this->assertConfigured($credentials);

        $request = Http::timeout(config('mobilesentrix.timeout'))
            ->connectTimeout(config('mobilesentrix.connect_timeout'))
            ->acceptJson();

        if ($transport === self::AUTH_TRANSPORT_OAUTH_HEADER) {
            $request = $request->withHeaders([
                'Authorization' => $this->authorizationHeader($credentials),
            ]);
        } else {
            $query = array_merge($query, $this->queryAuthParams($credentials));
        }

        $response = $request->get($this->url($path, $credentials), $query);

        return $this->decodeResponse($response, $path, $transport, $credentials);
    }

    private function decodeResponse(Response $response, string $path, string $authTransport, array $credentials): array
    {
        if (! $response->successful()) {
            $status = $response->status();
            $context = $this->safeFailureContext($path, $status, $authTransport, $credentials, $response);

            Log::warning(
                $status === 401
                    ? 'MobileSentrix API request rejected with HTTP 401.'
                    : 'MobileSentrix API request failed.',
                $context,
            );

            throw new MobileSentrixException(
                $status === 401
                    ? self::HTTP_401_MESSAGE
                    : 'MobileSentrix API request failed with HTTP '.$status.'.',
                $status,
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new MobileSentrixException('MobileSentrix returned a malformed JSON response.');
        }

        return $payload;
    }

    private function authorizationHeader(array $credentials): string
    {
        $values = [
            'oauth_consumer_key' => $credentials['consumer_key'],
            'oauth_token' => $credentials['access_token'],
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_signature' => $this->plaintextSignature(
                (string) $credentials['consumer_secret'],
                (string) $credentials['access_token_secret'],
            ),
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => Str::random(32),
            'oauth_version' => '1.0',
        ];

        return 'OAuth '.collect($values)
            ->map(fn ($value, $key) => $key.'="'.rawurlencode((string) $value).'"')
            ->implode(', ');
    }

    private function queryAuthParams(array $credentials): array
    {
        return [
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'access_token' => $credentials['access_token'],
            'access_token_secret' => $credentials['access_token_secret'],
        ];
    }

    private function plaintextSignature(string $consumerSecret, string $tokenSecret): string
    {
        return rawurlencode($consumerSecret).'&'.rawurlencode($tokenSecret);
    }

    private function url(string $path, array $credentials): string
    {
        return $credentials['base_url'].'/'.ltrim($path, '/');
    }

    private function assertConfigured(?array $credentials = null): void
    {
        $credentials ??= $this->credentials();

        $missing = collect([
            'base_url' => $credentials['base_url'],
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
            'access_token' => $credentials['access_token'],
            'access_token_secret' => $credentials['access_token_secret'],
        ])->filter(fn ($value) => blank($value))->keys()->all();

        if (empty($missing)) {
            return;
        }

        if (in_array('access_token', $missing, true) || in_array('access_token_secret', $missing, true)) {
            throw new MobileSentrixException('MobileSentrix access token is missing. Please authenticate MobileSentrix first from the admin panel.');
        }

        throw new MobileSentrixException('MobileSentrix API configuration is incomplete. Please verify the admin API settings.');
    }

    private function safeFailureContext(string $path, int $status, string $authTransport, array $credentials, Response $response): array
    {
        return array_filter([
            'endpoint_path' => '/'.ltrim($path, '/'),
            'status' => $status,
            'auth_transport' => $authTransport,
            'token_source' => $credentials['token_source'],
            'active_settings_row_id' => $credentials['settings_id'],
            'environment' => $credentials['environment'],
            'base_url' => $credentials['base_url'],
            'response_body' => $this->safeResponseBody($response, $credentials),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function safeResponseBody(Response $response, array $credentials): ?string
    {
        $body = trim((string) $response->body());

        if ($body === '') {
            return null;
        }

        $lowerBody = Str::lower($body);

        if (Str::contains($lowerBody, ['authorization', 'oauth_signature', 'consumer_secret', 'access_token_secret'])) {
            return null;
        }

        foreach (['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'] as $key) {
            $value = (string) ($credentials[$key] ?? '');

            if ($value !== '' && Str::contains($body, $value)) {
                return null;
            }
        }

        return Str::limit($body, 1000, '');
    }
}
