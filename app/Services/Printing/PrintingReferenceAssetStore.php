<?php

namespace App\Services\Printing;

use Illuminate\Support\Facades\Cache;

final class PrintingReferenceAssetStore
{
    public function put(string $sessionId, string $base64, string $mime = 'image/jpeg'): void
    {
        $base64 = trim($base64);
        if ($base64 === '') {
            return;
        }

        // Keep large binary/reference data out of the structured conversation
        // state. Database cache survives queue workers and container redeploys.
        Cache::store('database')->put(
            $this->key($sessionId),
            [
                'base64' => $base64,
                'mime' => strtolower(trim($mime)) ?: 'image/jpeg',
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addDays(30),
        );
    }

    /** @return array{base64:string,mime:string,updated_at?:string}|null */
    public function get(string $sessionId): ?array
    {
        $asset = Cache::store('database')->get($this->key($sessionId));
        if (! is_array($asset) || trim((string) ($asset['base64'] ?? '')) === '') {
            return null;
        }

        return $asset;
    }

    public function forget(string $sessionId): void
    {
        Cache::store('database')->forget($this->key($sessionId));
    }

    private function key(string $sessionId): string
    {
        return 'matbaa_ai:reference_asset:'.sha1($sessionId);
    }
}
