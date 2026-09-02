<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateEvidenceQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateEvidenceQualityOrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_and_bot_in_foreign_organization_cannot_persist_evidence_quality(): void
    {
        $bot = $this->seedIsolatedAccount();

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'evidence-quality-foreign-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => 'evidence-quality-foreign-org',
            'whatsapp_number' => '905551234567',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

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
                'media_findings' => [[
                    'document_type' => 'tapu senedi',
                    'property_type' => 'arsa',
                    'district' => 'Marmaris',
                    'area_sqm' => 1200,
                    'block_no' => '101',
                    'parcel_no' => '22',
                    'confidence_score' => 95,
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 75,
            'confidence_score' => 80,
        ]);

        $quality = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: true);

        $this->assertSame('out_of_scope', $quality['status']);
        $this->assertFalse($quality['sufficient_for_matching']);
        $this->assertArrayNotHasKey(
            'evidence_quality_intelligence',
            $profile->fresh()->data
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'evidence-org-isolation@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'evidence-org-isolation',
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
}
