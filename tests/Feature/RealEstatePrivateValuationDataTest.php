<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakOzelDegerlemeVerisi;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstatePrivateValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstatePrivateValuationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_accepted_human_verified_completed_sales_enter_private_dataset(): void
    {
        $operator = $this->seedAccounts();
        [$verifiedSeller] = $this->deal('verified', 1000, 3_000_000, 'completed', true, true, [
            'realistic_sale_min' => 2_800_000,
            'realistic_sale_max' => 3_200_000,
        ]);
        $this->deal('payment-missing', 1000, 3_100_000, 'completed', false, true);
        $this->deal('appointment-only', 1000, 3_200_000, 'appointment_scheduled', false, false);
        $this->foreignDeal();

        $service = app(RealEstatePrivateValuationService::class);
        $dataset = $service->dataset();
        $safe = $service->safeTransactions();
        $summary = $service->summary();

        $this->assertCount(1, $dataset);
        $this->assertSame($verifiedSeller->id, $dataset->first()['seller_profile_id']);
        $this->assertSame(3_000_000, $dataset->first()['actual_sale_price']);
        $this->assertSame(3000.0, $dataset->first()['actual_unit_price']);
        $this->assertTrue($dataset->first()['evidence']['human_verified']);
        $this->assertFalse($dataset->first()['automatic_outbound_allowed']);
        $this->assertFalse($dataset->first()['contains_customer_pii']);
        $this->assertFalse($dataset->first()['contains_private_seller_floor']);
        $this->assertFalse($dataset->first()['contains_raw_conversation']);

        $this->assertCount(1, $safe);
        $this->assertArrayNotHasKey('seller_profile_id', $safe->first());
        $this->assertArrayNotHasKey('investor_profile_id', $safe->first());
        $this->assertSame(1, $summary['verified_transactions']);
        $this->assertSame(1, $summary['calibration']['sample_count']);
        $this->assertSame(0.0, $summary['calibration']['mean_absolute_error_percent']);
        $this->assertFalse($summary['automatic_outbound_allowed']);
        $this->assertFalse($summary['contains_customer_payload']);

        $payload = json_encode($safe->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Gizli Satıcı verified', $payload);
        $this->assertStringNotContainsString('90555', $payload);
        $this->assertStringNotContainsString('minimum_price', $payload);
        $this->assertStringNotContainsString('seller_private_note', $payload);

        $this->actingAs($operator);
        $page = new EmlakOzelDegerlemeVerisi;
        $page->selectedProfileId = $verifiedSeller->id;

        $this->assertTrue(EmlakOzelDegerlemeVerisi::canAccess());
        $this->assertSame(1, $page->getSummaryProperty()['verified_transactions']);
        $this->assertCount(1, $page->getTransactionsProperty());
        $this->assertSame($verifiedSeller->id, $page->getSelectedProfileProperty()?->id);
    }

    public function test_three_similar_verified_sales_produce_robust_internal_band(): void
    {
        $this->seedAccounts();
        $this->deal('similar-one', 1000, 3_000_000);
        $this->deal('similar-two', 1000, 3_200_000);
        $this->deal('similar-three', 1000, 3_400_000);

        $targetConversation = $this->conversation(
            40,
            37,
            35,
            'private-valuation-target',
            'Hedef Satıcı',
            '905559999999',
        );
        $target = $this->profile($targetConversation, 40, 35, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'neighborhood' => 'Hisarönü',
            'area_sqm' => 1100,
            'zoning_status' => 'konut',
            'title_deed_type' => 'müstakil',
            'minimum_price' => 9_900_000,
            'seller_private_note' => 'Kesinlikle dışarı çıkmasın',
        ]);

        $estimate = app(RealEstatePrivateValuationService::class)->estimateFor($target);

        $this->assertSame('ready', $estimate['status']);
        $this->assertSame(3, $estimate['sample_count']);
        $this->assertSame(3, $estimate['strong_sample_count']);
        $this->assertSame(3200.0, $estimate['median_unit_price']);
        $this->assertSame(3_410_000, $estimate['suggested_sale_min']);
        $this->assertSame(3_520_000, $estimate['suggested_sale_mid']);
        $this->assertSame(3_630_000, $estimate['suggested_sale_max']);
        $this->assertSame('actual_closed_sale', $estimate['price_basis']);
        $this->assertTrue($estimate['guardrails']['operator_decision_support_only']);
        $this->assertFalse($estimate['guardrails']['customer_price_guarantee_allowed']);
        $this->assertFalse($estimate['guardrails']['automatic_negotiation_anchor_allowed']);
        $this->assertFalse($estimate['guardrails']['automatic_outbound_allowed']);
        $this->assertFalse($estimate['automatic_outbound_allowed']);
        $this->assertFalse($estimate['contains_customer_pii']);
        $this->assertFalse($estimate['contains_private_seller_floor']);
        $this->assertFalse($estimate['contains_raw_conversation']);

        $payload = json_encode($estimate, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Hedef Satıcı', $payload);
        $this->assertStringNotContainsString('905559999999', $payload);
        $this->assertStringNotContainsString('9900000', $payload);
        $this->assertStringNotContainsString('Kesinlikle dışarı çıkmasın', $payload);
    }

    public function test_fewer_than_three_samples_never_produces_a_price_band(): void
    {
        $this->seedAccounts();
        $this->deal('only-one', 1000, 3_000_000);
        $this->deal('only-two', 1000, 3_200_000);

        $targetConversation = $this->conversation(
            40,
            37,
            35,
            'private-valuation-early-target',
            'Erken Veri',
            '905558888888',
        );
        $target = $this->profile($targetConversation, 40, 35, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'neighborhood' => 'Hisarönü',
            'area_sqm' => 1000,
        ]);

        $estimate = app(RealEstatePrivateValuationService::class)->estimateFor($target);

        $this->assertSame('early_data', $estimate['status']);
        $this->assertSame(2, $estimate['sample_count']);
        $this->assertSame('minimum_sample_not_reached', $estimate['reason_code']);
        $this->assertNull($estimate['suggested_sale_min']);
        $this->assertNull($estimate['suggested_sale_mid']);
        $this->assertNull($estimate['suggested_sale_max']);
        $this->assertSame('Yetersiz örnek', $estimate['confidence_label']);
    }

    public function test_foreign_profile_and_unaccepted_closing_fail_closed(): void
    {
        $this->seedAccounts();
        $foreign = $this->foreignDeal();

        $conversation = $this->conversation(
            40,
            37,
            35,
            'private-valuation-unaccepted',
            'Kabul Kaydı Yok',
            '905557777777',
        );
        $investorConversation = $this->conversation(
            40,
            37,
            35,
            'private-valuation-unaccepted-investor',
            'Yatırımcı',
            '905557777778',
        );
        $seller = $this->profile($conversation, 40, 35, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1000,
        ]);
        $investor = $this->profile($investorConversation, 40, 35, 'investor', []);
        $seller->forceFill(['data' => array_merge($seller->data, [
            'transaction_closing_cases' => [
                (string) $investor->id => $this->closingState(
                    $seller->id,
                    $investor->id,
                    3_000_000,
                    'completed',
                    true,
                    true,
                    40,
                    37,
                    35,
                ),
            ],
        ])])->saveQuietly();

        $service = app(RealEstatePrivateValuationService::class);

        $this->assertCount(0, $service->dataset());
        $this->assertSame([], $service->estimateFor($foreign));
        $this->assertSame([], $service->estimateFor($investor));
    }

    private function deal(
        string $suffix,
        int $area,
        int $price,
        string $status = 'completed',
        bool $paymentVerified = true,
        bool $deedCompleted = true,
        array $valuation = [],
    ): array {
        $sellerConversation = $this->conversation(
            40,
            37,
            35,
            'private-seller-'.$suffix,
            'Gizli Satıcı '.$suffix,
            '90555'.str_pad((string) random_int(1, 9_999_999), 7, '0', STR_PAD_LEFT),
        );
        $investorConversation = $this->conversation(
            40,
            37,
            35,
            'private-investor-'.$suffix,
            'Gizli Yatırımcı '.$suffix,
            '90554'.str_pad((string) random_int(1, 9_999_999), 7, '0', STR_PAD_LEFT),
        );
        $seller = $this->profile($sellerConversation, 40, 35, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'neighborhood' => 'Hisarönü',
            'area_sqm' => $area,
            'zoning_status' => 'konut',
            'title_deed_type' => 'müstakil',
            'minimum_price' => 1_234_567,
            'seller_private_note' => 'Dışarı çıkmayacak gizli taban',
        ], $valuation);
        $investor = $this->profile($investorConversation, 40, 35, 'investor', []);

        $sellerConversation->activities()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'performed_by_user_id' => 40,
            'type' => 'real_estate_operator_call',
            'title' => 'Satıcı teklifi kabul etti',
            'description' => 'İnsan tarafından doğrulandı.',
            'new_value' => 'accepted',
            'meta' => [
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => $price,
                'automatic_outbound_allowed' => false,
            ],
        ]);

        $seller->forceFill(['data' => array_merge($seller->data, [
            'transaction_closing_cases' => [
                (string) $investor->id => $this->closingState(
                    $seller->id,
                    $investor->id,
                    $price,
                    $status,
                    $paymentVerified,
                    $deedCompleted,
                    40,
                    37,
                    35,
                ),
            ],
        ])])->saveQuietly();

        return [$seller->fresh(), $investor->fresh()];
    }

    private function foreignDeal(): RealEstateProfile
    {
        $sellerConversation = $this->conversation(
            41,
            38,
            36,
            'private-foreign-seller',
            'Foreign Satıcı',
            '905556666666',
        );
        $investorConversation = $this->conversation(
            41,
            38,
            36,
            'private-foreign-investor',
            'Foreign Yatırımcı',
            '905556666667',
        );
        $seller = $this->profile($sellerConversation, 41, 36, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1000,
        ]);
        $investor = $this->profile($investorConversation, 41, 36, 'investor', []);

        $sellerConversation->activities()->create([
            'user_id' => 41,
            'ai_bot_id' => 36,
            'performed_by_user_id' => 41,
            'type' => 'real_estate_operator_call',
            'title' => 'Foreign offer',
            'description' => 'Foreign',
            'new_value' => 'accepted',
            'meta' => [
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => 2_000_000,
            ],
        ]);

        $seller->forceFill(['data' => array_merge($seller->data, [
            'transaction_closing_cases' => [
                (string) $investor->id => $this->closingState(
                    $seller->id,
                    $investor->id,
                    2_000_000,
                    'completed',
                    true,
                    true,
                    41,
                    38,
                    36,
                ),
            ],
        ])])->saveQuietly();

        return $seller->fresh();
    }

    private function closingState(
        int $sellerId,
        int $investorId,
        int $price,
        string $status,
        bool $paymentVerified,
        bool $deedCompleted,
        int $user,
        int $organization,
        int $bot,
    ): array {
        return [
            'id' => $sellerId.':'.$investorId,
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'seller_profile_id' => $sellerId,
            'investor_profile_id' => $investorId,
            'status' => $status,
            'agreed_price' => $price,
            'final_payment_verified' => $paymentVerified,
            'deed_transfer_completed' => $deedCompleted,
            'closed_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'automatic_outbound_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
        ];
    }

    private function profile(
        ConversationControl $conversation,
        int $user,
        int $bot,
        string $type,
        array $data,
        array $valuation = [],
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => $valuation,
            'completeness_score' => 90,
            'confidence_score' => 80,
        ]));
    }

    private function conversation(
        int $user,
        int $organization,
        int $bot,
        string $session,
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
            'notes' => 'Gizli müşteri notu',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedAccounts(): User
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'private-valuation@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'private-valuation',
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
            'email' => 'foreign-private-valuation@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-private-valuation',
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
            'whatsapp_instance' => 'foreign-private-valuation',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $owner;
    }
}
