<?php

namespace App\Services\Payments;

class PaymentPayloadSanitizer
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_FRAGMENTS = [
        'access_token',
        'authorization',
        'bearer',
        'card',
        'client_secret',
        'cvc',
        'cvv',
        'password',
        'secret',
        'signature',
        'token',
    ];

    public function sanitize(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        return $this->sanitizeArray($payload);
    }

    private function sanitizeArray(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = self::REDACTED;

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeArray($value)
                : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
