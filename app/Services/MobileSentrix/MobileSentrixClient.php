<?php

namespace App\Services\MobileSentrix;

use App\Models\MobileSentrixApiSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MobileSentrixClient
{
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

    public function testConnection(): array
    {
        $categories = $this->categories();

        return [
            'ok' => true,
            'message' => 'MobileSentrix API connection succeeded.',
            'sample_count' => is_countable($categories) ? count($categories) : 0,
        ];
    }

    public function categories(): array
    {
        return $this->get('/api/rest/categories');
    }

    public function category(string|int $id): array
    {
        return $this->get('/api/rest/categories/'.$id);
    }

    public function products(array $query = []): array
    {
        return $this->get('/api/rest/products', $query);
    }

    public function product(string|int $id, array $query = []): array
    {
        return $this->get('/api/rest/products/'.$id, $query);
    }

    public function searchProducts(string $query, array $params = []): array
    {
        return $this->get('/api/rest/searchproduct', array_merge($params, ['q' => $query]));
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
        $settings = $this->activeSettings();

        return [
            'environment' => $credentials['environment'],
            'base_url' => $credentials['base_url'],
            'consumer_name' => filled($credentials['consumer_name']),
            'consumer_key' => filled($credentials['consumer_key']),
            'consumer_secret' => filled($credentials['consumer_secret']),
            'access_token' => filled($credentials['access_token']),
            'access_token_secret' => filled($credentials['access_token_secret']),
            'stored_access_tokens' => filled($settings?->access_token) && filled($settings?->access_token_secret),
            'callback_url' => $credentials['callback_url'],
            'sync_enabled' => (bool) config('mobilesentrix.sync_enabled'),
            'last_authenticated_at' => $settings?->last_authenticated_at,
        ];
    }

    public function credentials(): array
    {
        $settings = $this->activeSettings();

        return [
            'environment' => $settings?->environment ?: config('mobilesentrix.env'),
            'base_url' => rtrim((string) ($settings?->base_url ?: config('mobilesentrix.base_url')), '/'),
            'consumer_name' => $settings?->consumer_name ?: config('mobilesentrix.consumer_name'),
            'consumer_key' => $settings?->consumer_key ?: config('mobilesentrix.consumer_key'),
            'consumer_secret' => $settings?->consumer_secret ?: config('mobilesentrix.consumer_secret'),
            'access_token' => $settings?->access_token ?: config('mobilesentrix.access_token'),
            'access_token_secret' => $settings?->access_token_secret ?: config('mobilesentrix.access_token_secret'),
            'callback_url' => $settings?->callback_url ?: config('mobilesentrix.callback_url'),
        ];
    }

    private function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw new MobileSentrixException('MobileSentrix credentials are not fully configured.');
        }

        $response = Http::timeout(config('mobilesentrix.timeout'))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $this->authorizationHeader(),
            ])
            ->get($this->url($path), $query);

        return $this->decodeResponse($response);
    }

    private function decodeResponse(Response $response): array
    {
        if (! $response->successful()) {
            throw new MobileSentrixException('MobileSentrix API request failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new MobileSentrixException('MobileSentrix returned a malformed JSON response.');
        }

        return $payload;
    }

    private function authorizationHeader(): string
    {
        $credentials = $this->credentials();
        $consumerSecret = (string) $credentials['consumer_secret'];
        $tokenSecret = (string) $credentials['access_token_secret'];

        $values = [
            'oauth_consumer_key' => $credentials['consumer_key'],
            'oauth_token' => $credentials['access_token'],
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_signature' => $consumerSecret.'&'.$tokenSecret,
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => Str::random(32),
            'oauth_version' => '1.0a',
        ];

        return 'OAuth '.collect($values)
            ->map(fn ($value, $key) => $key.'="'.rawurlencode((string) $value).'"')
            ->implode(', ');
    }

    private function url(string $path): string
    {
        return $this->credentials()['base_url'].'/'.ltrim($path, '/');
    }

    private function activeSettings(): ?MobileSentrixApiSetting
    {
        return MobileSentrixApiSetting::query()->active()->latest('updated_at')->first();
    }
}
