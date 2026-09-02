<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOperatorAlert;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOperatorAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateSellerProtectionAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_urgency_seller_creates_privacy_safe_protection_alert(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'seller-protection-alert');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
                'asking_price' => 5_000_000,
                'minimum_price' => 4_300_000,
                'urgency' => 'high',
                'urgency_reason' => 'Acil borç kapatacağım. Telefon 05550000000.',
                'timeline' => 'bu hafta',
            ],
            'valuation' => [],
            'completeness_score' => 85,
            'confidence_score' => 80,
        ]);

        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());

        $alert = RealEstateOperatorAlert::query()
            ->where('user_id', 40)
            ->where('organization_id', 37)
            ->where('ai_bot_id', 35)
            ->where('real_estate_profile_id', $profile->id)
            ->where('type', 'seller_protection_attention')
            ->where('status', 'open')
            ->firstOrFail();

        $this->assertSame('medium', $alert->severity);
        $this->assertSame('Satıcı değer koruma incelemesi', $alert->title);
        $this->assertSame('high_explicit', $alert->payload['motivation_level']);
        $this->assertSame('unknown', $alert->payload['pricing_alignment']);
        $this->assertContains('high_explicit_urgency', $alert->payload['protection_reasons']);
        $this->assertFalse($alert->payload['private_floor_included']);
        $this->assertFalse($alert->payload['raw_urgency_reason_included']);

        $json = json_encode($alert->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('4300000', (string) $json);
        $this->assertStringNotContainsString('05550000000', (string) $json);
        $this->assertStringNotContainsString('Acil borç', (string) $json);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_foreign_organization_profile_cannot_create_or_resolve_isolated_alerts(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'seller-protection-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $isolatedConversation = $this->conversation($bot, 'seller-protection-isolated');
        $isolatedProfile = RealEstateProfile::query()->create([
            'conversation_control_id' => $isolatedConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 5_000_000,
                'urgency' => 'high',
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $isolatedAlert = RealEstateOperatorAlert::query()
            ->where('real_estate_profile_id', $isolatedProfile->id)
            ->where('type', 'seller_protection_attention')
            ->firstOrFail();

        $foreignConversation = $this->conversation($bot, 'seller-protection-foreign-conversation');
        DB::table('conversation_controls')
            ->where('id', $foreignConversation->id)
            ->update(['organization_id' => 38]);
        $foreignConversation->refresh();

        $foreignProfile = RealEstateProfile::query()->create([
            'conversation_control_id' => $foreignConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'asking_price' => 4_000_000,
                'urgency' => 'high',
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $this->assertSame(
            [],
            app(RealEstateOperatorAlertService::class)->sync($foreignProfile)
        );
        $this->assertDatabaseMissing('real_estate_operator_alerts', [
            'real_estate_profile_id' => $foreignProfile->id,
        ]);
        $this->assertSame('open', $isolatedAlert->fresh()->status);
        $this->assertNull($foreignConversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'seller-protection@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'seller-protection',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }

    private function conversation(AiBot $bot, string $sessionId): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
