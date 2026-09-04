<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WaiLeadDemoService
{
    public function create(array $lead): array
    {
        $company = trim((string) ($lead['company_name'] ?? ''));
        $description = trim((string) ($lead['company_description'] ?? ''));
        $role = trim((string) ($lead['role'] ?? 'sales')) ?: 'sales';

        if ($company === '' || $description === '') {
            return ['status' => 'waiting'];
        }

        $token = Str::random(48);

        Cache::put(
            'wai_lead_demo:'.$token,
            [
                'company_name' => $company,
                'company_description' => mb_substr($description, 0, 3000),
                'role' => in_array($role, ['sales', 'support', 'assistant', 'technical'], true)
                    ? $role
                    : 'sales',
                'source' => 'whatsapp_sales',
                'created_at' => now()->toIso8601String(),
            ],
            now()->addHours(6)
        );

        return [
            'status' => 'created',
            'token' => $token,
            'url' => url('/demo/lead/'.$token),
        ];
    }

    public function get(string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        $payload = Cache::get('wai_lead_demo:'.$token);

        return is_array($payload) ? $payload : null;
    }
}