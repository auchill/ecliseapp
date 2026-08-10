<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Single place where Stripe REST calls are configured.
 *
 * Pinning Stripe-Version keeps request/response shapes stable when the account's default
 * API version moves. Note that webhook payload shape is governed by the API version set on
 * the endpoint in the Stripe dashboard, not by this header.
 */
class StripeApiClient
{
    public const BASE_URL = 'https://api.stripe.com/v1';

    public function request(array $headers = []): PendingRequest
    {
        $version = (string) config('services.stripe.api_version');

        if ($version !== '') {
            $headers['Stripe-Version'] = $version;
        }

        return Http::withToken((string) config('services.stripe.secret'))
            ->withHeaders($headers)
            ->timeout((int) config('services.stripe.timeout', 20))
            ->connectTimeout((int) config('services.stripe.connect_timeout', 10))
            ->retry(2, 200, throw: false);
    }

    public function form(array $headers = []): PendingRequest
    {
        return $this->request($headers)->asForm();
    }

    public function url(string $path): string
    {
        return self::BASE_URL.'/'.ltrim($path, '/');
    }
}
