<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateNextBestActionService;
use App\Services\RealEstateSellerOfferPacketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateSellerOfferPacketIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_seller_packet_is_persisted_and_tagged_without_follow_up(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'offer-packet-ready');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1500,
            'asking_price' => 4_000_000,
            'block_no' => '123',
            'parcel_no' => '45',
            'zoning_status' => 'konut',
            'title_deed_type' => 'müstakil',
            'media_findings' => [
                [
                    'media_category' => 'property_photo',
                    'mime_type' => 'image/jpeg',
                    'confidence_score' => 88,
                ],
                [
                    'media_category' => 'title_deed',
                    'document_type' => 'Tapu belgesi',
                    'mime_type' => 'image/jpeg',
                    'confidence_score' => 92,
                ],
            ],
        ]);

        $summary = app(RealEstateSellerOfferPacketService::class)->sync($profile);
        $fresh = $profile->fresh();
        $conversation->refresh();

        $this->assertTrue($summary['ready_for_investor_offer']);
        $this->assertSame('ready', $summary['status']);
        $this->assertSame([], $summary['missing_critical_for_offer']);
        $this->assertTrue($summary['media_evidence']['has_property_photo']);
        $this->assertTrue($summary['media_evidence']['has_title_deed_evidence']);
        $this->assertArrayHasKey('seller_offer_packet_intelligence', $fresh->data);
        $this->assertContains('real_estate:offer_packet:ready', $conversation->etiketler());
        $this->assertNull($conversation->next_follow_up_at);
        $this->assertFalse($summary['guardrails']['follow_up_scheduling_allowed']);
    }

    public function test_legacy_tapu_is_recognized_but_actual_property_photo_is_still_requested(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'offer-packet-photo-gap');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'İstanbul',
            'district' => 'Silivri',
            'area_sqm' => 700,
            'asking_price' => 3_500_000,
            'block_no' => '77',
            'parcel_no' => '9',
            'zoning_status' => 'biliniyor',
            'media_findings' => [[
                'document_type' => 'Tapu senedi',
                'block_no' => '77',
                'parcel_no' => '9',
                'confidence_score' => 90,
            ]],
        ]);

        $summary = app(RealEstateSellerOfferPacketService::class)->sync($profile);

        $this->assertFalse($summary['ready_for_investor_offer']);
        $this->assertTrue($summary['media_evidence']['has_title_deed_evidence']);
        $this->assertTrue($summary['media_evidence']['has_parcel_evidence']);
        $this->assertFalse($summary['media_evidence']['has_property_photo']);
        $this->assertSame(['property_photo'], $summary['missing_critical_for_offer']);
        $this->assertStringContainsString(
            'fotoğraf',
            mb_strtolower((string) $summary['recommended_next_request'])
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_offer_packet_gap_becomes_next_best_action_after_hard_guards_are_clear(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'offer-packet-nba');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Bodrum',
            'area_sqm' => 900,
            'asking_price' => 8_000_000,
            'block_no' => '14',
            'parcel_no' => '21',
            'zoning_status' => 'konut',
            'title_deed_type' => 'müstakil',
            'seller_motivation_intelligence' => [
                'recommended_next_question' => null,
                'guardrails' => [
                    'follow_up_scheduling_allowed' => false,
                ],
            ],
            'decision_intelligence' => [
                'stage' => 'qualified',
                'missing_critical_data' => [],
                'ready_for_match' => true,
                'valuation_research_needed' => false,
                'valuation_integrity_needed' => false,
                'verification_status' => 'verified',
                'evidence_sufficient_for_matching' => true,
                'next_best_action' => 'Eski aksiyon',
            ],
        ]);

        app(RealEstateSellerOfferPacketService::class)->sync($profile);

        $plan = app(RealEstateNextBestActionService::class)
            ->process($conversation->fresh());

        $this->assertSame('complete_seller_offer_packet', $plan['action_code']);
        $this->assertSame('medium', $plan['priority']);
        $this->assertFalse($plan['blocking']);
        $this->assertStringContainsString('fotoğraf', mb_strtolower((string) $plan['single_question']));
        $this->assertContains('seller_offer_packet_incomplete', $plan['reason_codes']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_fails_closed_without_persisting_offer_intelligence(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'offer-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        // Production conversation creation normalizes the isolated bot back to
        // organization 37. Force a post-create organization drift here so the
        // service is tested against an actual foreign-organization row.
        $conversation = $this->conversation($bot, 37, 'offer-packet-foreign');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Bodrum',
            'area_sqm' => 900,
            'asking_price' => 8_000_000,
        ]);

        $summary = app(RealEstateSellerOfferPacketService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertArrayNotHasKey(
            'seller_offer_packet_intelligence',
            $profile->fresh()->data
        );
        $this->assertFalse(collect($conversation->fresh()->etiketler())
            ->contains(fn (string $tag): bool => str_starts_with($tag, 'real_estate:offer_packet:')));
    }

    public function test_privacy_safe_console_summary_contains_only_aggregate_packet_state(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'offer-packet-command');
        $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Bodrum',
            'area_sqm' => 900,
            'asking_price' => 8_000_000,
            'notes' => 'private-customer-note-should-never-appear',
        ]);

        $exitCode = Artisan::call('real-estate:offer-packets', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"seller_profiles": 1', $output);
        $this->assertStringNotContainsString('private-customer-note-should-never-appear', $output);
        $this->assertStringContainsString('"contains_customer_pii": false', $output);
        $this->assertStringContainsString('"follow_up_scheduling_allowed": false', $output);
    }

    private function profileQuietly(
        ConversationControl $conversation,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => 'seller',
                'data' => $data,
                'valuation' => [],
                'completeness_score' => 80,
                'confidence_score' => 80,
            ])
        );
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'offer-packet@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'offer-packet-isolated',
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

    private function conversation(
        AiBot $bot,
        int $organizationId,
        string $sessionId,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => $organizationId,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '905550000001',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
