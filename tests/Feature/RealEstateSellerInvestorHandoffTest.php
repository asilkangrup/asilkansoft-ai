<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOperatorAlert;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOperatorAlertService;
use App\Services\RealEstateSellerInvestorHandoffService;
use App\Services\RealEstateSellerOfferPacketService;
use App\Services\RealEstateValuationFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateSellerInvestorHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_fully_guarded_seller_with_filtered_candidate_becomes_manual_handoff_ready(): void
    {
        $bot = $this->seedIsolatedAccount('handoff-ready');
        $conversation = $this->conversation($bot, 'handoff-ready');
        $profile = $this->sellerProfileQuietly($conversation, $this->readyData());

        $valuation = app(RealEstateValuationFreshnessService::class)->stamp(
            $profile,
            $this->valuation(),
            CarbonImmutable::now()
        );
        RealEstateProfile::withoutEvents(fn () => $profile->forceFill(['valuation' => $valuation])->save());
        app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());

        $summary = app(RealEstateSellerInvestorHandoffService::class)
            ->sync($profile->fresh());
        $conversation->refresh();

        $this->assertSame('ready', $summary['status']);
        $this->assertTrue($summary['ready_for_operator_handoff']);
        $this->assertSame(1, $summary['candidate_count']);
        $this->assertSame('strong', $summary['strongest_match_grade']);
        $this->assertSame(91, $summary['strongest_match_score']);
        $this->assertFalse($summary['guardrails']['automatic_investor_outreach_allowed']);
        $this->assertFalse($summary['guardrails']['automatic_customer_follow_up_allowed']);
        $this->assertTrue($summary['guardrails']['human_review_required_before_investor_contact']);
        $this->assertContains('real_estate:handoff:ready', $conversation->etiketler());
        $this->assertNull($conversation->next_follow_up_at);
    }

    public function test_ready_handoff_opens_deduplicated_internal_operator_alert_without_sending_follow_up(): void
    {
        $bot = $this->seedIsolatedAccount('handoff-alert');
        $conversation = $this->conversation($bot, 'handoff-alert');
        $profile = $this->sellerProfileQuietly($conversation, $this->readyData());
        $valuation = app(RealEstateValuationFreshnessService::class)->stamp(
            $profile,
            $this->valuation(),
            CarbonImmutable::now()
        );
        RealEstateProfile::withoutEvents(fn () => $profile->forceFill(['valuation' => $valuation])->save());
        app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());
        app(RealEstateSellerInvestorHandoffService::class)->sync($profile->fresh());

        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());
        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());

        $alert = RealEstateOperatorAlert::query()
            ->where('alert_key', 'investor_offer_handoff:'.$profile->id)
            ->firstOrFail();

        $this->assertSame('investor_offer_handoff', $alert->type);
        $this->assertSame('open', $alert->status);
        $this->assertSame(1, RealEstateOperatorAlert::query()
            ->where('alert_key', 'investor_offer_handoff:'.$profile->id)
            ->count());
        $this->assertFalse((bool) data_get($alert->payload, 'automatic_investor_outreach_allowed', true));
        $this->assertFalse((bool) data_get($alert->payload, 'automatic_customer_follow_up_allowed', true));
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_incomplete_packet_stays_out_of_handoff_and_never_creates_ready_alert(): void
    {
        $bot = $this->seedIsolatedAccount('handoff-incomplete');
        $conversation = $this->conversation($bot, 'handoff-incomplete');
        $profile = $this->sellerProfileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 4200000,
        ]);

        app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());
        $summary = app(RealEstateSellerInvestorHandoffService::class)->sync($profile->fresh());
        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());

        $this->assertSame('packet_incomplete', $summary['status']);
        $this->assertFalse($summary['ready_for_operator_handoff']);
        $this->assertDatabaseMissing('real_estate_operator_alerts', [
            'real_estate_profile_id' => $profile->id,
            'type' => 'investor_offer_handoff',
            'status' => 'open',
        ]);
        $this->assertContains('real_estate:handoff:packet_incomplete', $conversation->fresh()->etiketler());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_fails_closed_and_does_not_persist_handoff(): void
    {
        $bot = $this->seedIsolatedAccount('handoff-foreign');
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'handoff-foreign-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 'handoff-foreign');
        DB::table('conversation_controls')->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();
        $profile = $this->sellerProfileQuietly($conversation, $this->readyData());

        $summary = app(RealEstateSellerInvestorHandoffService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertArrayNotHasKey('investor_offer_handoff_intelligence', $profile->fresh()->data);
        $this->assertFalse(collect($conversation->fresh()->etiketler())
            ->contains(fn (string $tag): bool => str_starts_with($tag, 'real_estate:handoff:')));
    }

    public function test_handoff_console_is_aggregate_only_and_contains_no_customer_note(): void
    {
        $bot = $this->seedIsolatedAccount('handoff-command');
        $conversation = $this->conversation($bot, 'handoff-command');
        $profile = $this->sellerProfileQuietly($conversation, [
            ...$this->readyData(),
            'notes' => 'private-customer-note-must-never-appear',
        ]);
        app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());
        app(RealEstateSellerInvestorHandoffService::class)->sync($profile->fresh());

        $exit = Artisan::call('real-estate:investor-handoffs', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"seller_profiles": 1', $output);
        $this->assertStringContainsString('"contains_customer_pii": false', $output);
        $this->assertStringContainsString('"automatic_investor_outreach_allowed": false', $output);
        $this->assertStringNotContainsString('private-customer-note-must-never-appear', $output);
    }

    private function readyData(): array
    {
        return [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 4200000,
            'block_no' => '123',
            'parcel_no' => '45',
            'zoning_status' => 'konut',
            'title_deed_type' => 'müstakil',
            'verification_intelligence' => [
                'status' => 'corroborated',
                'risk_score' => 10,
                'safe_to_match' => true,
                'conflicts' => [],
            ],
            'media_findings' => [
                [
                    'media_category' => 'title_deed',
                    'document_type' => 'Tapu senedi',
                    'confidence_score' => 92,
                    'property_type' => 'arsa',
                    'district' => 'Marmaris',
                    'area_sqm' => 1200,
                    'block_no' => '123',
                    'parcel_no' => '45',
                ],
                [
                    'media_category' => 'property_photo',
                    'document_type' => 'Taşınmaz fotoğrafı',
                    'confidence_score' => 88,
                ],
            ],
            'opportunity_matches' => [[
                'seller_profile_id' => 1,
                'investor_profile_id' => 2,
                'match_score' => 91,
                'grade' => 'strong',
            ]],
            'opportunity_match_summary' => [
                'count' => 1,
                'strongest_score' => 91,
                'strongest_grade' => 'strong',
                'valuation_freshness_enforced' => true,
                'verification_filtered' => true,
            ],
        ];
    }

    private function valuation(): array
    {
        return [
            'market_min' => 3700000,
            'market_max' => 4300000,
            'quick_sale_min' => 3400000,
            'quick_sale_max' => 3800000,
            'investor_buy_min' => 3200000,
            'investor_buy_max' => 3900000,
            'confidence_score' => 82,
            'sources' => [
                'https://example.test/listing/1',
                'https://example.test/listing/2',
            ],
            'comparables' => [
                [
                    'source' => 'Emsal 1',
                    'url' => 'https://example.test/listing/1',
                    'listing_price' => 4100000,
                    'area_sqm' => 1150,
                    'location' => 'Muğla Marmaris',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
                [
                    'source' => 'Emsal 2',
                    'url' => 'https://example.test/listing/2',
                    'listing_price' => 4300000,
                    'area_sqm' => 1250,
                    'location' => 'Muğla Marmaris',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
            ],
        ];
    }

    private function sellerProfileQuietly(ConversationControl $conversation, array $data): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => 'seller',
                'data' => $data,
                'valuation' => [],
                'completeness_score' => 90,
                'confidence_score' => 85,
            ])
        );
    }

    private function seedIsolatedAccount(string $session): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => $session.'@example.test',
            'password' => Hash::make('test-password'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-'.$session,
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

    private function conversation(AiBot $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
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
