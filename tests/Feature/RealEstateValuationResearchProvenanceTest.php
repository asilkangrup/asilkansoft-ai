<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\RealEstateValuationResearchEvent;
use App\Models\User;
use App\Services\RealEstateValuationFreshnessService;
use App\Services\RealEstateValuationResearchContextService;
use App\Services\RealEstateValuationResearchLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateValuationResearchProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_context_only_exposes_valuation_safe_property_fields(): void
    {
        [, $conversation] = $this->seedScope('context-safe');
        $profile = $this->profile($conversation, [
            'minimum_price' => 3_250_000,
            'urgency' => 'high',
            'urgency_reason' => 'borç kapatacağım',
            'notes' => '0555 111 22 33 private note',
            'seller_motivation_intelligence' => [
                'confidential_price_flexibility' => ['band' => 'high'],
            ],
            'opportunity_matches' => [[
                'investor_profile_id' => 999,
                'match_score' => 90,
            ]],
        ]);

        $context = app(RealEstateValuationResearchContextService::class)->build(
            profile: $profile,
            forceRefresh: true,
            freshness: [
                'status' => 'stale',
                'quality' => 'insufficient',
                'reasons' => ['expired'],
                'researched_at' => now()->subDays(8)->toIso8601String(),
            ],
        );

        $this->assertNotNull($context);
        $this->assertSame('arsa', $context['property_profile']['property_type']);
        $this->assertSame('Muğla', $context['property_profile']['city']);
        $this->assertSame(4_200_000, $context['property_profile']['asking_price']);
        $this->assertTrue($context['research_request']['force_refresh']);
        $this->assertSame(['expired'], $context['previous_research']['reason_codes']);

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('minimum_price', $encoded);
        $this->assertStringNotContainsString('3250000', $encoded);
        $this->assertStringNotContainsString('borç kapatacağım', $encoded);
        $this->assertStringNotContainsString('0555 111 22 33', $encoded);
        $this->assertStringNotContainsString('seller_motivation_intelligence', $encoded);
        $this->assertStringNotContainsString('opportunity_matches', $encoded);
        $this->assertStringNotContainsString('customer_question', $encoded);
    }

    public function test_pending_fact_conflict_blocks_external_valuation_research_context(): void
    {
        [, $conversation] = $this->seedScope('context-conflict');
        $profile = $this->profile($conversation, [
            'fact_consistency_intelligence' => [
                'status' => 'confirmation_required',
                'pending_count' => 1,
                'pending_conflicts' => [[
                    'field' => 'district',
                    'proposed_value' => 'Bodrum',
                ]],
            ],
        ]);

        $context = app(RealEstateValuationResearchContextService::class)->build(
            profile: $profile,
            forceRefresh: true,
            freshness: ['status' => 'stale'],
        );

        $this->assertNull($context);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_research_ledger_is_isolated_idempotent_and_does_not_store_raw_sources_or_private_floor(): void
    {
        [, $conversation] = $this->seedScope('ledger');
        $profile = $this->profile($conversation, [
            'minimum_price' => 3_250_000,
            'urgency_reason' => 'nakit ihtiyacı',
        ]);
        $freshness = app(RealEstateValuationFreshnessService::class);
        $valuation = $freshness->stamp($profile, $this->valuation());

        $service = app(RealEstateValuationResearchLedgerService::class);
        $first = $service->record($profile, $valuation, 'gpt-5.4', true);
        $second = $service->record($profile, $valuation, 'gpt-5.4', true);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RealEstateValuationResearchEvent::query()->count());

        $event = RealEstateValuationResearchEvent::query()->firstOrFail();
        $this->assertSame(40, $event->user_id);
        $this->assertSame(37, $event->organization_id);
        $this->assertSame(35, $event->ai_bot_id);
        $this->assertSame($profile->id, $event->real_estate_profile_id);
        $this->assertTrue($event->forced_refresh);
        $this->assertSame(2, $event->comparable_count);
        $this->assertSame(2, $event->usable_comparable_count);
        $this->assertSame('safe', $event->integrity_status);

        $encoded = json_encode($event->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('https://example.test/listing/1', $encoded);
        $this->assertStringNotContainsString('https://other.test/listing/2', $encoded);
        $this->assertStringNotContainsString('3250000', $encoded);
        $this->assertStringNotContainsString('nakit ihtiyacı', $encoded);
        $this->assertStringNotContainsString('0555', $encoded);

        $telemetry = $service->telemetry24h();
        $this->assertSame(1, $telemetry['events']);
        $this->assertSame(1, $telemetry['forced_refreshes']);
        $this->assertSame(1, $telemetry['match_safe_integrity']);
        $this->assertSame(1, $telemetry['unique_profiles']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_profile_cannot_write_research_ledger(): void
    {
        [$bot] = $this->seedScope('foreign-org');

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'foreign-org-valuation',
            'status' => 'active',
        ]);

        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => $bot->id,
            'session_id' => 'foreign-org-valuation',
            'whatsapp_number' => '905550000099',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
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
                'asking_price' => 4_200_000,
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);
        $valuation = app(RealEstateValuationFreshnessService::class)
            ->stamp($profile, $this->valuation());

        $event = app(RealEstateValuationResearchLedgerService::class)
            ->record($profile, $valuation, 'gpt-5.4', false);

        $this->assertNull($event);
        $this->assertSame(0, RealEstateValuationResearchEvent::query()->count());
    }

    private function seedScope(string $suffix): array
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => $suffix.'@example.test',
            'password' => Hash::make('test-password'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-'.$suffix,
            'status' => 'active',
        ]);
        $bot = AiBot::query()->forceCreate([
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
        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $suffix,
            'whatsapp_number' => '905551112233',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);

        return [$bot, $conversation];
    }

    private function profile(ConversationControl $conversation, array $extra = []): RealEstateProfile
    {
        return RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'neighborhood' => 'Hisarönü',
                'area_sqm' => 1200,
                'asking_price' => 4_200_000,
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
                'location_url' => 'https://maps.example/property',
                ...$extra,
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 82,
        ]);
    }

    private function valuation(): array
    {
        return [
            'market_min' => 3_700_000,
            'market_max' => 4_300_000,
            'quick_sale_min' => 3_400_000,
            'quick_sale_max' => 3_800_000,
            'investor_buy_min' => 3_200_000,
            'investor_buy_max' => 3_900_000,
            'confidence_score' => 82,
            'sources' => [
                'https://example.test/listing/1',
                'https://other.test/listing/2',
            ],
            'comparables' => [
                [
                    'source' => 'Emsal 1',
                    'url' => 'https://example.test/listing/1',
                    'listing_price' => 4_100_000,
                    'area_sqm' => 1150,
                    'location' => 'Muğla Marmaris Hisarönü',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
                [
                    'source' => 'Emsal 2',
                    'url' => 'https://other.test/listing/2',
                    'listing_price' => 4_300_000,
                    'area_sqm' => 1250,
                    'location' => 'Muğla Marmaris Hisarönü',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
            ],
        ];
    }
}
