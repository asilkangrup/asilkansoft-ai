<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakDonusumRaporu;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateConversionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateConversionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_connects_isolated_ad_leads_to_offers_closings_and_collections(): void
    {
        $operator = $this->seedAccounts();

        $sellerConversation = $this->conversation(
            40,
            37,
            35,
            'conversion-seller',
            'instagram',
            'qualified',
            82,
            'Gizli Satıcı',
            '905551111111',
        );
        $investorConversation = $this->conversation(
            40,
            37,
            35,
            'conversion-investor',
            'facebook',
            'new',
            20,
            'Gizli Yatırımcı',
            '905552222222',
        );

        $seller = $this->profile($sellerConversation, 40, 35, 'seller', [
            'minimum_price' => 4_500_000,
            'seller_private_note' => 'Yatırımcıya açıklanmaz',
            'marketing_attribution' => [
                'source' => 'instagram',
                'campaign' => 'Acil Arsa Satışı',
                'ad_set' => 'İstanbul Satıcı',
                'creative' => 'Video 01',
            ],
        ], 85);
        $investor = $this->profile($investorConversation, 40, 35, 'investor', [
            'marketing_attribution' => [
                'source' => 'facebook',
                'campaign' => 'Nakit Yatırımcı',
            ],
        ], 45);

        $sellerConversation->activities()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'performed_by_user_id' => $operator->id,
            'type' => 'real_estate_operator_call',
            'title' => 'Satıcı teklifi kabul etti',
            'description' => 'İnsan tarafından doğrulandı.',
            'new_value' => 'accepted',
            'meta' => [
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => 3_000_000,
                'automatic_outbound_allowed' => false,
            ],
        ]);

        $seller->forceFill(['data' => array_merge($seller->data, [
            'transaction_closing_cases' => [
                (string) $investor->id => [
                    'id' => $seller->id.':'.$investor->id,
                    'user_id' => 40,
                    'organization_id' => 37,
                    'ai_bot_id' => 35,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => 'completed',
                    'agreed_price' => 3_000_000,
                    'final_payment_verified' => true,
                    'deed_transfer_completed' => true,
                    'automatic_outbound_allowed' => false,
                    'contains_private_seller_floor' => false,
                    'contains_customer_pii' => false,
                ],
            ],
            'commission_collection_cases' => [
                (string) $investor->id => [
                    'id' => $seller->id.':'.$investor->id,
                    'user_id' => 40,
                    'organization_id' => 37,
                    'ai_bot_id' => 35,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => 'collected',
                    'agreed_price' => 3_000_000,
                    'seller_rate_percent' => 2,
                    'buyer_rate_percent' => 2,
                    'seller_due_amount' => 60_000,
                    'buyer_due_amount' => 60_000,
                    'total_due_amount' => 120_000,
                    'seller_collected_amount' => 60_000,
                    'buyer_collected_amount' => 60_000,
                    'total_collected_amount' => 120_000,
                    'seller_remaining_amount' => 0,
                    'buyer_remaining_amount' => 0,
                    'total_remaining_amount' => 0,
                    'operator_note' => 'Gizli muhasebe notu',
                    'seller_receipt_reference' => 'SATICI-GIZLI',
                    'buyer_receipt_reference' => 'ALICI-GIZLI',
                ],
            ],
        ])])->saveQuietly();

        $report = app(RealEstateConversionReportService::class)->report(
            now()->subDays(7)->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(2, $report['totals']['leads']);
        $this->assertSame(1, $report['totals']['seller_leads']);
        $this->assertSame(1, $report['totals']['investor_leads']);
        $this->assertSame(1, $report['totals']['qualified_leads']);
        $this->assertSame(1, $report['totals']['offered_leads']);
        $this->assertSame(1, $report['totals']['accepted_deals']);
        $this->assertSame(1, $report['totals']['closed_deals']);
        $this->assertSame(120_000, $report['totals']['commission_due_amount']);
        $this->assertSame(120_000, $report['totals']['commission_collected_amount']);
        $this->assertSame(50.0, $report['totals']['closing_rate']);

        $instagram = collect($report['rows'])->firstWhere('campaign', 'Acil Arsa Satışı');
        $this->assertSame('Instagram', $instagram['source']);
        $this->assertSame(1, $instagram['leads']);
        $this->assertSame(1, $instagram['qualified_leads']);
        $this->assertSame(1, $instagram['offered_leads']);
        $this->assertSame(1, $instagram['closed_deals']);
        $this->assertSame(120_000, $instagram['commission_collected_amount']);

        $payload = json_encode($report, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Gizli Satıcı', $payload);
        $this->assertStringNotContainsString('Gizli Yatırımcı', $payload);
        $this->assertStringNotContainsString('905551111111', $payload);
        $this->assertStringNotContainsString('4_500_000', $payload);
        $this->assertStringNotContainsString('Yatırımcıya açıklanmaz', $payload);
        $this->assertStringNotContainsString('Gizli muhasebe', $payload);
        $this->assertFalse($report['automatic_outbound_allowed']);
        $this->assertFalse($report['contains_customer_payload']);

        $this->actingAs($operator);
        $page = new EmlakDonusumRaporu;
        $page->from = now()->subDays(7)->toDateString();
        $page->until = now()->toDateString();
        $page->selectedProfileId = $seller->id;

        $this->assertTrue(EmlakDonusumRaporu::canAccess());
        $this->assertCount(2, $page->getCandidatesProperty());
        $this->assertSame(2, $page->getReportProperty()['totals']['leads']);
        $this->assertSame($seller->id, $page->getSelectedProfileProperty()?->id);
    }

    public function test_operator_can_complete_campaign_attribution_without_message_or_follow_up(): void
    {
        $operator = $this->seedAccounts();
        $conversation = $this->conversation(
            40,
            37,
            35,
            'conversion-attribution',
            'whatsapp',
            'new',
            10,
            'Kaynak Testi',
            '905553333333',
        );
        $profile = $this->profile($conversation, 40, 35, 'seller', [
            'minimum_price' => 900_000,
        ], 30);
        $conversation->forceFill(['next_follow_up_at' => null])->save();

        $result = app(RealEstateConversionReportService::class)->updateAttribution($profile, [
            'source' => 'google_ads',
            'campaign' => 'Satılık Tarla Arama',
            'ad_set' => 'Türkiye Geneli',
            'creative' => 'Başlık A',
        ], $operator);

        $this->assertSame('google_ads', $result['source']);
        $this->assertSame('Google Ads', $result['source_label']);
        $this->assertSame('Satılık Tarla Arama', $result['campaign']);
        $this->assertFalse($result['automatic_outbound_allowed']);
        $this->assertFalse($result['customer_follow_up_allowed']);
        $this->assertFalse($result['contains_customer_pii']);
        $this->assertFalse($result['contains_private_seller_floor']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);

        $stored = data_get($profile->fresh()->data, 'marketing_attribution');
        $this->assertSame('google_ads', $stored['source']);
        $this->assertSame('Satılık Tarla Arama', $stored['campaign']);

        $activity = $conversation->activities()
            ->where('type', 'real_estate_marketing_attribution')
            ->firstOrFail();

        $this->assertSame(40, $activity->user_id);
        $this->assertSame(35, $activity->ai_bot_id);
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_outbound_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'follow_up_scheduling_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_customer_pii'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_private_seller_floor'));
        $this->assertTrue((bool) data_get($activity->meta, 'has_campaign'));
        $this->assertArrayNotHasKey('campaign', $activity->meta);
        $this->assertArrayNotHasKey('ad_set', $activity->meta);
        $this->assertArrayNotHasKey('creative', $activity->meta);
    }

    public function test_legacy_channel_is_used_when_campaign_data_is_missing(): void
    {
        $this->seedAccounts();
        $conversation = $this->conversation(
            40,
            37,
            35,
            'conversion-legacy',
            'instagram',
            'new',
            0,
            'Legacy Lead',
            '905554444444',
        );
        $this->profile($conversation, 40, 35, 'seller', [], 0);

        $report = app(RealEstateConversionReportService::class)->report();

        $this->assertSame(1, $report['totals']['leads']);
        $this->assertSame(1, $report['totals']['unattributed_leads']);
        $this->assertSame('Instagram', $report['rows'][0]['source']);
        $this->assertSame('Belirtilmedi', $report['rows'][0]['campaign']);
    }

    public function test_foreign_tenant_is_excluded_and_cannot_be_mutated(): void
    {
        $this->seedAccounts();

        $exactConversation = $this->conversation(
            40,
            37,
            35,
            'conversion-exact',
            'whatsapp',
            'new',
            0,
            'Exact Lead',
            '905555555555',
        );
        $this->profile($exactConversation, 40, 35, 'seller', [], 0);

        $foreignConversation = $this->conversation(
            41,
            38,
            36,
            'conversion-foreign',
            'instagram',
            'won',
            100,
            'Foreign Lead',
            '905556666666',
        );
        $foreign = $this->profile($foreignConversation, 41, 36, 'seller', [
            'marketing_attribution' => [
                'source' => 'instagram',
                'campaign' => 'Foreign Campaign',
            ],
        ], 100);

        $report = app(RealEstateConversionReportService::class)->report();

        $this->assertSame(1, $report['totals']['leads']);
        $this->assertStringNotContainsString('Foreign Campaign', json_encode($report, JSON_THROW_ON_ERROR));
        $this->assertSame([], app(RealEstateConversionReportService::class)->storedAttribution($foreign));

        $this->expectException(HttpException::class);
        app(RealEstateConversionReportService::class)->updateAttribution($foreign, [
            'source' => 'instagram',
            'campaign' => 'Mutate Foreign',
            'ad_set' => null,
            'creative' => null,
        ], User::query()->findOrFail(40));
    }

    private function profile(
        ConversationControl $conversation,
        int $user,
        int $bot,
        string $type,
        array $data,
        int $completeness,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
            'completeness_score' => $completeness,
        ]));
    }

    private function conversation(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $channel,
        string $leadStatus,
        int $leadScore,
        string $name,
        string $phone,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => $phone,
            'customer_name' => $name,
            'channel' => $channel,
            'notes' => 'Gizli müşteri notu',
            'tags' => [],
            'lead_status' => $leadStatus,
            'lead_score' => $leadScore,
            'lead_temperature' => $leadScore >= 70 ? 'hot' : 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedAccounts(): User
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'conversion@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'conversion',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $owner->organizations()->syncWithoutDetaching([
            37 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
        ]);
        AiBot::query()->forceCreate([
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

        $foreign = User::query()->forceCreate([
            'id' => 41,
            'name' => 'Foreign',
            'email' => 'foreign-conversion@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-conversion',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $foreign->organizations()->syncWithoutDetaching([
            38 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
        ]);
        AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 41,
            'name' => 'Foreign',
            'company_name' => 'Foreign',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'foreign-conversion',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $owner;
    }
}
