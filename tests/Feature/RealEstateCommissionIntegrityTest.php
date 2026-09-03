<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCommissionIntegrityService;
use App\Services\RealEstateCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateCommissionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_closing_without_collection_record_requires_human_review_only(): void
    {
        $this->seedReadyScope();
        [$seller, $investor] = $this->deal('completed', 2_000_000);

        $assessment = app(RealEstateCommissionIntegrityService::class)->assess($seller, $investor);

        $this->assertSame('warning', $assessment['risk_level']);
        $this->assertContains('collection_record_missing', $assessment['signal_codes']);
        $this->assertSame('record_collection_status', $assessment['next_best_action']);
        $this->assertTrue($assessment['human_confirmation_required']);
        $this->assertFalse($assessment['automatic_outbound_allowed']);
        $this->assertFalse($assessment['automatic_payment_request_allowed']);
        $this->assertFalse($assessment['customer_follow_up_allowed']);
        $this->assertFalse($assessment['contains_private_seller_floor']);
        $this->assertFalse($assessment['contains_customer_pii']);
        $this->assertNull($seller->conversation->fresh()->next_follow_up_at);
        $this->assertNull($investor->conversation->fresh()->next_follow_up_at);
    }

    public function test_closing_price_change_after_collection_is_detected_as_critical_financial_mismatch(): void
    {
        $owner = $this->seedReadyScope();
        [$seller, $investor] = $this->deal('completed', 1_000_000);

        app(RealEstateCommissionService::class)->save($seller, $investor, [
            'seller_collected_amount' => 20_000,
            'buyer_collected_amount' => 20_000,
            'seller_collected_at' => today()->toDateString(),
            'buyer_collected_at' => today()->toDateString(),
            'seller_receipt_reference' => 'SAFE-SELLER-REF',
            'buyer_receipt_reference' => 'SAFE-BUYER-REF',
            'operator_note' => 'Özel muhasebe notu',
        ], $owner);

        $seller = $seller->fresh();
        $data = is_array($seller->data) ? $seller->data : [];
        $data['transaction_closing_cases'][(string) $investor->id]['agreed_price'] = 1_200_000;
        $data['transaction_closing_cases'][(string) $investor->id]['updated_at'] = now()->toIso8601String();
        $seller->forceFill(['data' => $data])->saveQuietly();

        $assessment = app(RealEstateCommissionIntegrityService::class)->assess(
            $seller->fresh(),
            $investor->fresh(),
        );

        $this->assertSame('critical', $assessment['risk_level']);
        $this->assertContains('agreed_price_mismatch', $assessment['signal_codes']);
        $this->assertContains('commission_due_mismatch', $assessment['signal_codes']);
        $this->assertSame('reconcile_agreed_price_and_commission', $assessment['next_best_action']);
        $this->assertFalse($assessment['automatic_outbound_allowed']);
        $this->assertFalse($assessment['customer_follow_up_allowed']);

        $health = app(RealEstateCommissionIntegrityService::class)->health();
        $this->assertSame('human_attention_required', $health['state']);
        $this->assertSame(1, $health['counts']['critical']);
        $this->assertSame(1, $health['counts']['integrity_mismatch']);
        $this->assertFalse($health['automatic_outbound_allowed']);
        $this->assertFalse($health['automatic_payment_request_allowed']);
        $this->assertFalse($health['customer_follow_up_allowed']);
        $this->assertFalse($health['contains_customer_payload']);
        $this->assertFalse($health['contains_customer_pii']);
        $this->assertFalse($health['contains_private_seller_floor']);
        $this->assertFalse($health['live_traffic_blocking']);

        $safe = json_encode($health, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Özel muhasebe notu', $safe);
        $this->assertStringNotContainsString('SAFE-SELLER-REF', $safe);
        $this->assertStringNotContainsString('Test Satıcı', $safe);
        $this->assertStringNotContainsString('90555', $safe);
    }

    public function test_unfinished_closing_without_collection_is_waiting_not_a_false_financial_alarm(): void
    {
        $this->seedReadyScope();
        [$seller, $investor] = $this->deal('appointment_scheduled', 1_500_000);

        $assessment = app(RealEstateCommissionIntegrityService::class)->assess($seller, $investor);
        $health = app(RealEstateCommissionIntegrityService::class)->health();

        $this->assertSame('waiting', $assessment['risk_level']);
        $this->assertContains('closing_not_completed', $assessment['signal_codes']);
        $this->assertSame(1, $health['counts']['waiting_closing']);
        $this->assertSame(0, $health['counts']['critical']);
        $this->assertSame(0, $health['counts']['warning']);
        $this->assertSame('healthy', $health['state']);
    }

    public function test_commission_health_command_and_public_health_are_privacy_safe_and_nonblocking(): void
    {
        $this->seedReadyScope();
        $this->deal('completed', 2_500_000);

        $exit = Artisan::call('real-estate:commission-health', ['--json' => true]);
        $this->assertSame(0, $exit);

        $commandPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($commandPayload['ready']);
        $this->assertSame('collection_attention_required', $commandPayload['state']);
        $this->assertSame(1, $commandPayload['counts']['record_missing']);
        $this->assertFalse($commandPayload['contains_customer_payload']);
        $this->assertFalse($commandPayload['contains_customer_pii']);
        $this->assertFalse($commandPayload['live_traffic_blocking']);

        $response = $this->getJson('/api/real-estate/health');
        $response
            ->assertOk()
            ->assertJsonPath('checks.commission_health_observable', true)
            ->assertJsonPath('commission_health.ready', true)
            ->assertJsonPath('commission_health.state', 'collection_attention_required')
            ->assertJsonPath('commission_health.counts.record_missing', 1)
            ->assertJsonPath('commission_health.automatic_outbound_allowed', false)
            ->assertJsonPath('commission_health.customer_follow_up_allowed', false)
            ->assertJsonPath('commission_health.contains_customer_payload', false)
            ->assertJsonPath('commission_health.contains_customer_pii', false)
            ->assertJsonPath('commission_health.live_traffic_blocking', false)
            ->assertJsonPath('ready_for_live_traffic', true);

        $this->assertNotContains(
            'commission_health_observable',
            $response->json('blocking_checks')
        );

        $safe = $response->getContent();
        $this->assertStringNotContainsString('Test Satıcı', $safe);
        $this->assertStringNotContainsString('Gizli müşteri notu', $safe);
        $this->assertStringNotContainsString('3.100.000', $safe);
    }

    private function deal(string $closingStatus, int $agreedPrice): array
    {
        $sellerConversation = $this->conversation('commission-integrity-seller-'.uniqid(), 'Test Satıcı');
        $investorConversation = $this->conversation('commission-integrity-investor-'.uniqid(), 'Test Yatırımcı');
        $seller = $this->profile($sellerConversation, 'seller', [
            'city' => 'İstanbul',
            'district' => 'Başakşehir',
            'property_type' => 'Arsa',
            'minimum_price' => '3.100.000',
            'seller_private_note' => 'Yatırımcıya açıklanmayacak',
        ]);
        $investor = $this->profile($investorConversation, 'investor');

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

    private function closingState(int $sellerId, int $investorId, string $status, int $agreedPrice): array
    {
        $completed = $status === 'completed';

        return [
            'id' => $sellerId.':'.$investorId,
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'seller_profile_id' => $sellerId,
            'investor_profile_id' => $investorId,
            'status' => $status,
            'status_label' => $completed ? 'Devir ve ödeme tamamlandı' : 'Tapu randevusu planlandı',
            'agreed_price' => $agreedPrice,
            'title_deed_verified' => true,
            'identity_authority_verified' => true,
            'encumbrance_checked' => true,
            'tax_fee_checked' => true,
            'payment_method_confirmed' => true,
            'appointment_at' => now()->addHour()->toIso8601String(),
            'deposit_received' => false,
            'final_payment_verified' => $completed,
            'deed_transfer_completed' => $completed,
            'closed_at' => $completed ? now()->toIso8601String() : null,
            'updated_at' => now()->toIso8601String(),
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_private_seller_floor' => false,
            'contains_customer_pii' => false,
        ];
    }

    private function profile(
        ConversationControl $conversation,
        string $type,
        array $data = [],
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
        ]));
    }

    private function conversation(string $session, string $name): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $session,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9_999_999), 7, '0', STR_PAD_LEFT),
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

    private function seedReadyScope(): User
    {
        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        Http::fake([
            'https://evolution.example.test/instance/connectionState/emlak-ai-35' =>
                Http::response(['instance' => ['state' => 'open']], 200),
        ]);

        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'commission-integrity@example.test',
            'password' => Hash::make('test'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'commission-integrity',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString(
                    'commission-integrity-webhook-secret-123456789'
                ),
            ],
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
            'openai_api_key' => 'sk-proj-commission-integrity-test-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connected',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
            'subscription_status' => 'active',
            'group_routing_enabled' => false,
        ]);

        return $owner;
    }
}
