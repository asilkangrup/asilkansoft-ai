<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateMatchEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMatchLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateMatchLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_final_safe_matches_are_persisted_and_removed_matches_get_a_lifecycle_event(): void
    {
        $bot = $this->seedIsolatedBot();
        [$seller, $sellerConversation] = $this->profile(
            $bot,
            'seller',
            'match-ledger-seller',
            '905551010101'
        );
        [$investor, $investorConversation] = $this->profile(
            $bot,
            'investor',
            'match-ledger-investor',
            '905552020202'
        );

        $sellerData = $seller->data;
        $sellerData['verification_intelligence'] = [
            'status' => 'corroborated',
            'risk_score' => 20,
            'safe_to_match' => true,
        ];
        $sellerData['opportunity_matches'] = [
            $this->safeMatch($seller, $investor, $sellerConversation, $investorConversation),
        ];
        $sellerData['opportunity_match_summary'] = [
            'count' => 1,
            'strongest_score' => 88,
            'strongest_grade' => 'strong',
            // Intermediate/raw state: must not enter the durable ledger yet.
            'updated_at' => now()->toIso8601String(),
        ];
        $seller->update(['data' => $sellerData]);

        $this->assertDatabaseCount('real_estate_match_events', 0);

        $sellerData['opportunity_match_summary']['verification_filtered'] = true;
        $sellerData['opportunity_match_summary']['evidence_quality_filtered'] = true;
        $seller->update(['data' => $sellerData]);

        $this->assertDatabaseCount('real_estate_match_events', 1);
        $event = RealEstateMatchEvent::query()->firstOrFail();

        $this->assertSame(40, (int) $event->user_id);
        $this->assertSame(37, (int) $event->organization_id);
        $this->assertSame(35, (int) $event->ai_bot_id);
        $this->assertSame($seller->id, (int) $event->seller_profile_id);
        $this->assertSame($investor->id, (int) $event->investor_profile_id);
        $this->assertSame('active', $event->status);
        $this->assertSame(88, $event->match_score);
        $this->assertSame('strong', $event->grade);
        $this->assertFalse((bool) ($event->safety['official_sale_price_verified'] ?? true));
        $this->assertFalse((bool) ($event->safety['contact_data_stored'] ?? true));
        $this->assertFalse((bool) ($event->safety['seller_private_floor_stored'] ?? true));
        $this->assertStringNotContainsString('05551234567', json_encode($event->reasons));
        $this->assertStringNotContainsString('investor@example.test', json_encode($event->reasons));
        $this->assertStringContainsString('[redacted-phone]', json_encode($event->reasons));
        $this->assertStringContainsString('[redacted-email]', json_encode($event->reasons));

        // Saving the same final state is idempotent.
        $seller->touch();
        $this->assertDatabaseCount('real_estate_match_events', 1);

        $seller->refresh();
        $sellerData = $seller->data;
        $sellerData['opportunity_matches'] = [];
        $sellerData['opportunity_match_summary'] = [
            'count' => 0,
            'verification_filtered' => true,
            'evidence_quality_filtered' => true,
            'updated_at' => now()->toIso8601String(),
        ];
        $seller->update(['data' => $sellerData]);

        $this->assertDatabaseCount('real_estate_match_events', 2);
        $latest = RealEstateMatchEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('removed', $latest->status);
        $this->assertSame(
            'no_longer_in_final_safe_matches',
            $latest->safety['removal_reason'] ?? null
        );

        $summary = app(RealEstateMatchLedgerService::class)->summaryForProfile($seller->fresh());
        $this->assertSame(0, $summary['active_count']);
        $this->assertTrue($summary['final_safe_matches_only']);
        $this->assertFalse($summary['contact_data_stored']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_profile_cannot_write_match_ledger_events(): void
    {
        $bot = $this->seedIsolatedBot();
        [$investor, $investorConversation] = $this->profile(
            $bot,
            'investor',
            'match-ledger-safe-investor',
            '905553030303'
        );

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Organization',
            'slug' => 'foreign-real-estate-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        // Conversation creation normalizes the isolated bot to organization 37.
        // Force the persisted fixture to organization 38 afterward so this test
        // exercises the actual cross-organization isolation boundary.
        $foreignConversation = ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'session_id' => 'foreign-match-ledger-session',
            'whatsapp_number' => '905554040404',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
        $foreignConversation->forceFill(['organization_id' => 38])->saveQuietly();
        $foreignConversation->refresh();
        $this->assertSame(38, (int) $foreignConversation->organization_id);

        $foreignSeller = RealEstateProfile::query()->create([
            'conversation_control_id' => $foreignConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 4000000,
                'verification_intelligence' => [
                    'status' => 'corroborated',
                    'risk_score' => 10,
                    'safe_to_match' => true,
                ],
            ],
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 85,
        ]);

        $data = $foreignSeller->data;
        $data['opportunity_matches'] = [
            $this->safeMatch(
                $foreignSeller,
                $investor,
                $foreignConversation,
                $investorConversation
            ),
        ];
        $data['opportunity_match_summary'] = [
            'count' => 1,
            'verification_filtered' => true,
            'evidence_quality_filtered' => true,
        ];
        $foreignSeller->update(['data' => $data]);

        $this->assertDatabaseCount('real_estate_match_events', 0);
        $this->assertSame(
            [],
            app(RealEstateMatchLedgerService::class)->sync($foreignSeller->fresh())
        );
        $this->assertNull($foreignConversation->fresh()->next_follow_up_at);
    }

    private function safeMatch(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        ConversationControl $sellerConversation,
        ConversationControl $investorConversation,
    ): array {
        return [
            'candidate_profile_id' => $investor->id,
            'candidate_conversation_id' => $investorConversation->id,
            'candidate_role' => 'investor',
            'seller_profile_id' => $seller->id,
            'seller_conversation_id' => $sellerConversation->id,
            'investor_profile_id' => $investor->id,
            'investor_conversation_id' => $investorConversation->id,
            'match_score' => 88,
            'grade' => 'strong',
            'estimated_transaction_price' => 3700000,
            'reasons' => [
                'Bütçe ve bölge uyumlu; 05551234567 / investor@example.test dahili iletişim bilgisi taşınmamalı.',
            ],
            'risks' => ['Resmi tapu kontrolü işlem öncesi ayrıca yapılmalı.'],
            'valuation_freshness' => [
                'status' => 'fresh',
                'quality' => 'good',
                'source_count' => 2,
                'comparable_count' => 2,
                'researched_at' => now()->subHour()->toIso8601String(),
                'expires_at' => now()->addDays(6)->toIso8601String(),
            ],
            'comparable_integrity' => [
                'status' => 'safe',
                'quality' => 'good',
                'usable_comparable_count' => 2,
                'distinct_source_host_count' => 2,
                'price_basis' => 'asking',
                'official_sale_price_verified' => false,
            ],
        ];
    }

    private function profile(
        AiBot $bot,
        string $type,
        string $session,
        string $whatsapp
    ): array {
        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
            'whatsapp_number' => $whatsapp,
            'tags' => ['business:real_estate_'.$type],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);

        $data = $type === 'seller'
            ? [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 4100000,
            ]
            : [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'budget_max' => 4500000,
                'financing' => 'cash',
            ];

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 85,
        ]);

        return [$profile, $conversation];
    }

    private function seedIsolatedBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'match-ledger@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'isolated-match-ledger',
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
