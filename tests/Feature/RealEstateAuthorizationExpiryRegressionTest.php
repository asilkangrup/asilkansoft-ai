<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateAuthorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateAuthorizationExpiryRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_ready_authorization_fails_closed_after_expiration_without_manual_update(): void
    {
        CarbonImmutable::setTestNow('2026-09-03 10:00:00');
        $profile = $this->isolatedSeller([
            'authorization_control' => [
                'status' => 'ready',
                'mandate_type' => 'exclusive',
                'signed_at' => '2026-09-01',
                'expires_at' => '2026-09-04',
                'seller_presentation_consent' => true,
                'commission_terms_acknowledged' => true,
                'title_owner_confirmed' => true,
                'authorization_document_present' => true,
                'legal_review_required' => false,
                'history' => [],
            ],
        ]);

        $before = app(RealEstateAuthorizationService::class)->readiness($profile);
        $this->assertTrue($before['ready']);
        $this->assertSame('ready', $before['status']);

        CarbonImmutable::setTestNow('2026-09-05 10:00:00');
        $after = app(RealEstateAuthorizationService::class)->readiness($profile->fresh());

        $this->assertFalse($after['ready']);
        $this->assertFalse($after['ready_for_investor_presentation']);
        $this->assertFalse($after['ready_for_offer_collection']);
        $this->assertSame('expired', $after['status']);
        $this->assertContains('authorization_expired', $after['blocking_reason_codes']);
        $this->assertFalse($after['automatic_outbound_allowed']);
        $this->assertFalse($after['customer_follow_up_allowed']);
    }

    public function test_foreign_scope_never_receives_authorization_readiness(): void
    {
        $this->seedIdentity(41, 38, 36, 'foreign-auth-expiry');
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 41,
            'organization_id' => 38,
            'ai_bot_id' => 36,
            'session_id' => 'foreign-auth-expiry',
            'whatsapp_number' => '905559999999',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 41,
            'ai_bot_id' => 36,
            'profile_type' => 'seller',
            'data' => ['authorization_control' => ['status' => 'ready']],
            'valuation' => [],
        ]));

        $this->assertSame([], app(RealEstateAuthorizationService::class)->readiness($profile));
    }

    private function isolatedSeller(array $data): RealEstateProfile
    {
        $this->seedIdentity(40, 37, 35, 'auth-expiry');
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'auth-expiry',
            'whatsapp_number' => '905551111111',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
        ]));
    }

    private function seedIdentity(int $userId, int $organizationId, int $botId, string $slug): void
    {
        User::query()->forceCreate([
            'id' => $userId,
            'name' => 'Account '.$userId,
            'email' => $slug.'@example.test',
            'password' => Hash::make('test-password'),
        ]);
        Organization::query()->forceCreate([
            'id' => $organizationId,
            'owner_user_id' => $userId,
            'name' => 'Org '.$organizationId,
            'slug' => $slug,
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        AiBot::query()->forceCreate([
            'id' => $botId,
            'user_id' => $userId,
            'name' => 'Bot '.$botId,
            'company_name' => 'Test',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => $botId === 35 ? 'emlak-ai-35' : $slug,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }
}
