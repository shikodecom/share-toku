<?php

namespace App\Services;

use Illuminate\Support\Str;

class EventToken
{
    public function issue(array $claims): array
    {
        $expires = now()->addSeconds(config('sharetoku.event_token_ttl'));
        $claims += ['iat' => now()->timestamp, 'exp' => $expires->timestamp, 'nonce' => (string) Str::ulid(), 'version' => 1];
        $body = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $this->key(), true)), '+/', '-_'), '=');

        return ['token' => $body.'.'.$signature, 'expires_at' => $expires->toIso8601String()];
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || strlen($token) > 4096) {
            return null;
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $parts[0], $this->key(), true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $parts[1])) {
            return null;
        }
        $claims = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        if (! is_array($claims) || ($claims['exp'] ?? 0) < now()->timestamp || ($claims['version'] ?? null) !== 1) {
            return null;
        }

        return $claims;
    }

    private function key(): string
    {
        $key = config('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
}
