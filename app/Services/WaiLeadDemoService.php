<?php

namespace App\Services;

use App\Models\WaiDemoLead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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
        $payload = [
            'company_name' => $company,
            'company_description' => mb_substr($description, 0, 3000),
            'role' => in_array($role, ['sales', 'support', 'assistant', 'technical'], true)
                ? $role
                : 'sales',
            'source' => 'whatsapp_sales',
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put(
            'wai_lead_demo:'.$token,
            $payload,
            now()->addHours(6)
        );

        if (Schema::hasTable('wai_demo_leads')) {
            WaiDemoLead::query()->create([
                'token' => $token,
                'company_name' => $company,
                'company_description' => $payload['company_description'],
                'source' => $payload['source'],
            ]);
        }

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

    public function markOpened(string $token): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        WaiDemoLead::query()
            ->where('token', $token)
            ->whereNull('first_opened_at')
            ->update(['first_opened_at' => now()]);
    }

    public function markTestMessage(string $token): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        $lead = WaiDemoLead::query()->where('token', $token)->first();

        if (! $lead) {
            return;
        }

        $lead->increment('test_message_count');
        $lead->forceFill([
            'first_opened_at' => $lead->first_opened_at ?: now(),
            'last_tested_at' => now(),
        ])->save();
    }

    public function markConnectStarted(string $token, ?AiBot $bot = null): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        WaiDemoLead::query()->where('token', $token)->update([
            'whatsapp_connect_started_at' => now(),
            'temporary_bot_id' => $bot?->id,
            'whatsapp_instance' => $bot?->whatsapp_instance,
        ]);
    }

    public function markConnected(string $token, ?AiBot $bot = null): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        $lead = WaiDemoLead::query()->where('token', $token)->first();

        if (! $lead) {
            return;
        }

        $lead->forceFill([
            'whatsapp_connect_started_at' => $lead->whatsapp_connect_started_at ?: now(),
            'whatsapp_connected_at' => $lead->whatsapp_connected_at ?: now(),
            'temporary_bot_id' => $bot?->id ?: $lead->temporary_bot_id,
            'whatsapp_instance' => $bot?->whatsapp_instance ?: $lead->whatsapp_instance,
        ])->save();
    }
}
