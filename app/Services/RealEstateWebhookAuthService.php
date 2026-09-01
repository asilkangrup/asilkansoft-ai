<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class RealEstateWebhookAuthService
{
    private const SECRET_SETTING = 'real_estate_webhook_secret';

    public function secret(): ?string
    {
        $organization = Organization::query()
            ->whereKey(RealEstateIsolationService::ORGANIZATION_ID)
            ->where('owner_user_id', RealEstateIsolationService::USER_ID)
            ->where('status', 'active')
            ->first(['settings']);

        $encrypted = is_array($organization?->settings)
            ? ($organization->settings[self::SECRET_SETTING] ?? null)
            : null;

        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            $secret = Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }

        $secret = trim($secret);

        return $secret !== '' ? $secret : null;
    }

    public function configured(): bool
    {
        return $this->secret() !== null;
    }

    public function verifyRequest(Request $request): bool
    {
        $token = trim((string) $request->bearerToken());
        $secret = $this->secret();

        if ($token === '' || $secret === null) {
            return false;
        }

        return $this->verifyToken($token, $secret);
    }

    public function verifyToken(string $token, string $secret): bool
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3) {
            return false;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);

        if (! is_array($header) || ! is_array($payload)) {
            return false;
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return false;
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac(
                'sha256',
                $encodedHeader.'.'.$encodedPayload,
                $secret,
                true
            )
        );

        if (! hash_equals($expectedSignature, $encodedSignature)) {
            return false;
        }

        $now = time();
        $issuedAt = is_numeric($payload['iat'] ?? null)
            ? (int) $payload['iat']
            : null;
        $expiresAt = is_numeric($payload['exp'] ?? null)
            ? (int) $payload['exp']
            : null;

        if ($issuedAt === null || $expiresAt === null) {
            return false;
        }

        if ($issuedAt > ($now + 60) || $expiresAt < $now) {
            return false;
        }

        if (($expiresAt - $issuedAt) > 900 || ($now - $issuedAt) > 900) {
            return false;
        }

        return ($payload['app'] ?? null) === 'evolution'
            && ($payload['action'] ?? null) === 'webhook';
    }

    private function decodeJson(string $value): ?array
    {
        $decoded = $this->base64UrlDecode($value);

        if ($decoded === null) {
            return null;
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
