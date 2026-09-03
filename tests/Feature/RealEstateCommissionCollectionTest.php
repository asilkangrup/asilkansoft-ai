<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakKomisyonTahsilat;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateCommissionCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_deal_tracks_two_percent_from_each_party_without_outbound_or_private_data(): void
    {
        $operator = $this->seedAccounts();
        [$seller, $investor] = $this->deal('completed', 3_000_000);

        $seller->conversation->forceFill(['next_follow_up_at' => null])->save();
        $investor->conversation->forceFill(['next_follow_up_at' => null])->save();

        $partial = app(RealEstateCommissionService::class)->save($seller, $investor, [
            'seller_collected_amount' => 30_000,
            'buyer_collected_amount' => 60_000,
            'seller_collected_at' => today()->toDateString(),
            'buyer_collected_at' => today()->toDateString(),
            'seller_receipt_reference' => 'SATICI-MAKBUZ-01',
            'buyer_receipt_reference' => 'ALICI-MAKBUZ-01',
            'operator_note' => 'Yalnız ekip içinde görülecek muhasebe notu.',
        ], $operator);

        $this->assertSame(60_000, $partial['seller_due_amount']);
        $this->assertSame(60_000, $partial['buyer_due_amount']);
        $this->assertSame(120_000, $partial['total_due_amount']);
        $this->assertSame(90_000, $partial['total_collected_amount']);
        $this->assertSame(30_000, $partial['total_remaining_amount']);
        $this->assertSame('partial', $partial['status']);
        $this->assertFalse($partial['automatic_outbound_allowed']);
        $this->assertFalse($partial['automatic_payment_request_allowed']);
        $this->assertFalse($partial['customer_follow_up_allowed']);
        $this->assertFalse($partial['contains_private_seller_floor']);
        $this->assertFalse($partial['contains_customer_pii']);
        $this->assertArrayNotHasKey('operator_note', $partial);
        $this->assertArrayNotHasKey('seller_receipt_reference', $partial);
        $this->assertArrayNotHasKey('buyer_receipt_reference', $partial);
        $this->assertArrayNotHasKey('history', $partial);

        $safePayload = json_encode($partial, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('3.100.000', $safePayload);
        $this->assertStringNotContainsString('muhasebe notu', $safePayload);
        $this->assertStringNotContainsString('MAKBUZ', $safePayload);

        $complete = app(RealEstateCommissionService::class)->save($seller->fresh(), $investor->fresh(), [
            'seller_collected_amount' => 60_000,
            'buyer_collected_amount' => 60_000,
            'seller_collected_at' => today()->toDateString(),
            'buyer_collected_at' => today()->toDateString(),
            'seller_receipt_reference' => 'SATICI-MAKBUZ-02',
            'buyer_receipt_reference' => 'ALICI-MAKBUZ-02',
            'operator_note' => 'Tahsilat insan tarafından doğrulandı.',
        ], $operator);

        $this->assertSame('collected', $complete['status']);
        $this->assertSame(0, $complete['total_remaining_amount']);
        $this->assertNull($seller->conversation->fresh()->next_follow_up_at);
        $this->assertNull($investor->conversation->fresh()->next_follow_up_at);

        $activity = $seller->conversation->activities()
            ->where('type', 'real_estate_commission_collection')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(40, $activity->user_id);
        $this->assertSame(35, $activity->ai_bot_id);
        $this->assertSame(2, (int) data_get($activity->meta, 'seller_rate_percent'));
        $this->assertSame(2, (int) data_get($activity->meta, 'buyer_rate_percent'));
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_outbound_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_payment_request_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'follow_up_scheduling_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_private_seller_floor'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_customer_pii'));

        $this->actingAs($operator);
        $page = new EmlakKomisyonTahsilat;
        $page->selectedKey = $seller->id.':'.$investor->id;

        $this->assertTrue(EmlakKomisyonTahsilat::canAccess());
        $this->assertCount(1, $page->getDealsProperty());
        $this->assertSame('collected', $page->getCommissionProperty()['status']);
        $this->assertSame(120_000, $page->getTotalsProperty()['total_collected_amount']);
        $this->assertSame(1, $page->getTotalsProperty()['fully_collected_count']);
    }

    public function test_collection_cannot_be_recorded_before_human_verified_closing(): void
    {
        $operator = $this->seedAccounts();
        [$seller, $investor] = $this->deal('appointment_scheduled', 2_000_000);

        $this->expectException(ValidationException::class);
        app(RealEstateCommissionService::class)->save($seller, $investor, [
            'seller_collected_amount' => 40_000,
            'buyer_collected_amount' => 0,
            'seller_collected_at' => today()->toDateString(),
            'buyer_collected_at' => null,
            'seller_receipt_reference' => null,
            'buyer_receipt_reference' => null,
            'operator_note' => null,
        ], $operator);
    }

    public function test_collection_cannot_exceed_the_exact_two_percent_entitlement(): void
    {
        $operator = $this->seedAccounts();
        [$seller, $investor] = $this->deal('completed', 1_000_000);

        try {
            app(RealEstateCommissionService::class)->save($seller, $investor, [
                'seller_collected_amount' => 20_001,
                'buyer_collected_amount' => 0,
                'seller_collected_at' => today()->toDateString(),
                'buyer_collected_at' => null,
                'seller_receipt_reference' => null,
                'buyer_receipt_reference' => null,
                'operator_note' => null,
            ], $operator);

            $this->fail('Fazla tahsilat doğrulama hatası üretmeliydi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seller_collected_amount', $exception->errors());
        }

        $this->assertNull(app(RealEstateCommissionService::class)->recordForPair($seller->id, $investor->id));
    }

    public function test_foreign_tenant_commission_data_is_rejected_and_never_listed(): void
    {
        $operator = $this->seedAccounts();
        [$seller, $investor] = $this->deal('completed', 1_500_000);
        [$foreignSeller, $foreignInvestor] = $this->foreignDeal();

        $this->assertNull(app(RealEstateCommissionService::class)->recordForPair(
            $foreignSeller->id,
            $foreignInvestor->id,
        ));

        $this->actingAs($operator);
        $page = new EmlakKomisyonTahsilat;

        $this->assertCount(1, $page->getDealsProperty());
        $this->assertSame($seller->id.':'.$investor->id, $page->getDealsProperty()->first()['key']);

        $this->expectException(HttpException::class);
        app(RealEstateCommissionService::class)->save($foreignSeller, $foreignInvestor, [
            'seller_collected_amount' => 0,
            'buyer_collected_amount' => 0,
            'seller_collected_at' => null,
            'buyer_collected_at' => null,
            'seller_receipt_reference' => null,
            'buyer_receipt_reference' => null,
            'operator_note' => null,
        ], $operator);
    }

    private function deal(string $closingStatus, int $agreedPrice): array
    {
        $sellerConversation = $this->conversation(40, 37, 35, 'commission-seller-'.uniqid());
        $investorConversation = $this->conversation(40, 37, 35, 'commission-investor-'.uniqid());
        $seller = $this->profile($sellerConversation, 40, 35, 'seller', [
            'city' => 'İstanbul',
            'district' => 'Başakşehir',
            'property_type' => 'Arsa',
            'minimum_price' => '3.100.000',
            'seller_private_note' => 'Yatırımcıya açıklanmayacak',
        ]);
        $investor = $this->profile($investorConversation, 40, 35, 'investor');

        $sellerConversation->activities()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'performed_by_user_id' => 40,
            'type' => 'real_estate_operator_call',
            'title' => 'Satıcı teklifi kabul etti',
            'description' => 'İnsan tarafından kaydedildi.',
            'new_value' => 'accepted',
            'meta' => [
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => $agreedPrice,
                'automatic_outbound_allowed' => false,
            ],
        ]);

        $seller->forceFill(['data' => array_merge($seller->data, [
            'transaction_closing_cases' => [
                (string) $investor->id => $this->closingState(
                    $seller->id,
                    $investor->id,
                    $closingStatus,
                    $agreedPrice,
                ),
            ],
        ])])->saveQuietly();

        return [$seller->fresh(), $investor->fresh()];
    }

    private function foreignDeal(): array
    {
        $sellerConversation = $this->conversation(41, 38, 36, 'foreign-commission-seller');
        $investorConversation = $this->conversation(41, 38, 36, 'foreign-commission-investor');
        $seller = $this->profile($sellerConversation, 41, 36, 'seller');
        $investor = $this->profile($investorConversation, 41, 36, 'investor');

        $sellerConversation->activities()->create([
            'user_id' => 41,
            'ai_bot_id' => 36,
            'performed_by_user_id' => 41,
            'type' => 'real_estate_operator_call',
            'title' => 'Foreign accepted offer',
            'description' => 'Foreign tenant data',
            'new_value' => 'accepted',
            'meta' => [
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => 900_000,
            ],
        ]);

        $seller->forceFill(['data' => [
            'transaction_closing_cases' => [
                (string) $investor->id => [
                    'user_id' => 41,
                    'organization_id' => 38,
                    'ai_bot_id' => 36,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => 'completed',
                    'agreed_price' => 900_000,
                ],
            ],
            'commission_collection_cases' => [
                (string) $investor->id => [
                    'user_id' => 41,
                    'organization_id' => 38,
                    'ai_bot_id' => 36,
                    'seller_profile_id' => $seller->id,
                    'investor_profile_id' => $investor->id,
                    'status' => 'collected',
                    'total_collected_amount' => 36_000,
                ],
            ],
        ]])->saveQuietly();

        return [$seller->fresh(), $investor->fresh()];
    }

    private function closingState(int $sellerId, int $investorId, string $status, int $agreedPrice): array
    {
        return [
            'id' => $sellerId.':'.$investorId,
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'seller_profile_id' => $sellerId,
            'investor_profile_id' => $investorId,
            'status' => $status,
            'status_label' => $status === 'completed' ? 'Devir ve ödeme tamamlandı' : 'Tapu randevusu planlandı',
            'agreed_price' => $agreedPrice,
            'title_deed_verified' => true,
            'identity_authority_verified' => true,
            'encumbrance_checked' => true,
            'tax_fee_checked' => true,
            'payment_method_confirmed' => true,
            'appointment_at' => now()->toIso8601String(),
            'deposit_received' => false,
            'final_payment_verified' => $status === 'completed',
            'deed_transfer_completed' => $status === 'completed',
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_private_seller_floor' => false,
            'contains_customer_pii' => false,
        ];
    }

    private function profile(
        ConversationControl $conversation,
        int $user,
        int $bot,
        string $type,
        array $data = [],
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
        ]));
    }

    private function conversation(int $user, int $organization, int $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'customer_name' => $user === 40 ? 'Test Müşteri' : 'Foreign Müşteri',
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
            'email' => 'commission@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'commission',
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
            'email' => 'foreign-commission@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-commission',
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
            'whatsapp_instance' => 'foreign-commission',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $owner;
    }
}
