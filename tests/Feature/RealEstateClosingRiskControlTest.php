<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\CrmActivity;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateClosingRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateClosingRiskControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_missed_appointment_and_payment_transfer_inconsistency_are_critical(): void
    {
        [$seller, $investor] = $this->seedAcceptedPair();
        $case = $this->caseState($seller, $investor, [
            'appointment_at' => now()->subHour()->toIso8601String(),
            'final_payment_verified' => false,
            'deed_transfer_completed' => true,
        ]);

        $risk = app(RealEstateClosingRiskService::class)->assess($seller, $investor, $case);

        $this->assertSame('critical', $risk['risk_level']);
        $this->assertContains('appointment_missed_or_unconfirmed', $risk['signal_codes']);
        $this->assertContains('deed_transfer_without_final_payment', $risk['signal_codes']);
        $this->assertSame('resolve_payment_transfer_inconsistency', $risk['next_best_action']);
        $this->assertTrue($risk['human_confirmation_required']);
        $this->assertFalse($risk['automatic_outbound_allowed']);
        $this->assertFalse($risk['customer_follow_up_allowed']);
    }

    public function test_expired_authorization_remains_critical_even_when_case_has_other_warnings(): void
    {
        [$seller, $investor] = $this->seedAcceptedPair(false);
        $data = $seller->data;
        $data['authorization_control'] = $this->readyAuthorization();
        $data['authorization_control']['expires_at'] = now()->subDay()->toDateString();
        $seller->forceFill(['data' => $data])->saveQuietly();
        $case = $this->caseState($seller, $investor, [
            'appointment_at' => null,
        ]);

        $risk = app(RealEstateClosingRiskService::class)->assess($seller->fresh(), $investor, $case);

        $this->assertSame('critical', $risk['risk_level']);
        $this->assertContains('authorization_expired', $risk['signal_codes']);
        $this->assertSame('renew_seller_authorization', $risk['next_best_action']);
    }

    public function test_completed_case_is_not_reported_as_operational_risk(): void
    {
        [$seller, $investor] = $this->seedAcceptedPair();
        $case = $this->caseState($seller, $investor, [
            'status' => 'completed',
            'final_payment_verified' => true,
            'deed_transfer_completed' => true,
            'closed_at' => now()->toIso8601String(),
        ]);

        $risk = app(RealEstateClosingRiskService::class)->assess($seller, $investor, $case);

        $this->assertSame('completed', $risk['risk_level']);
        $this->assertSame(['closing_completed'], $risk['signal_codes']);
        $this->assertSame('archive_completed_case', $risk['next_best_action']);
    }

    public function test_health_is_aggregate_only_and_foreign_scope_is_ignored(): void
    {
        [$seller, $investor] = $this->seedAcceptedPair();
        $this->caseState($seller, $investor, [
            'appointment_at' => now()->subHour()->toIso8601String(),
        ], true);

        $foreignConversation = $this->conversation(41, 38, 36, 'foreign-risk', 'Foreign Customer');
        $foreignSeller = $this->profile($foreignConversation, 41, 36, 'seller', [
            'authorization_control' => $this->readyAuthorization(),
            'private_seller_floor' => 9_999_999,
        ]);
        $foreignInvestorConversation = $this->conversation(41, 38, 36, 'foreign-risk-investor', 'Foreign Investor');
        $foreignInvestor = $this->profile($foreignInvestorConversation, 41, 36, 'investor');
        $this->acceptedActivity($foreignConversation, $foreignSeller, $foreignInvestor, 99_000_000, 41, 36);

        $health = app(RealEstateClosingRiskService::class)->health();
        $encoded = json_encode($health, JSON_UNESCAPED_UNICODE);

        $this->assertSame(1, data_get($health, 'counts.accepted_deals'));
        $this->assertSame(1, data_get($health, 'counts.cases_opened'));
        $this->assertSame(1, data_get($health, 'counts.critical'));
        $this->assertSame(40, data_get($health, 'scope.user_id'));
        $this->assertSame(37, data_get($health, 'scope.organization_id'));
        $this->assertSame(35, data_get($health, 'scope.bot_id'));
        $this->assertSame('emlak-ai-35', data_get($health, 'scope.instance'));
        $this->assertFalse($health['automatic_outbound_allowed']);
        $this->assertFalse($health['customer_follow_up_allowed']);
        $this->assertFalse($health['live_traffic_blocking']);
        $this->assertStringNotContainsString('Foreign Customer', $encoded);
        $this->assertStringNotContainsString('9999999', $encoded);
        $this->assertStringNotContainsString('905550000000', $encoded);
    }

    public function test_privacy_safe_closing_health_command_returns_success_for_human_workload(): void
    {
        [$seller, $investor] = $this->seedAcceptedPair();
        $this->caseState($seller, $investor, [
            'appointment_at' => now()->subHour()->toIso8601String(),
        ], true);

        $exit = Artisan::call('real-estate:closing-health', ['--json' => true]);
        $output = trim(Artisan::output());

        $this->assertSame(0, $exit);
        $this->assertJson($output);
        $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('human_attention_required', $payload['state']);
        $this->assertFalse($payload['contains_customer_payload']);
        $this->assertFalse($payload['contains_customer_pii']);
        $this->assertStringNotContainsString('Risk Seller', $output);
        $this->assertStringNotContainsString('905550000000', $output);
    }

    private function seedAcceptedPair(bool $readyAuthorization = true): array
    {
        $this->seedAccounts();
        $sellerConversation = $this->conversation(40, 37, 35, 'risk-seller', 'Risk Seller');
        $investorConversation = $this->conversation(40, 37, 35, 'risk-investor', 'Risk Investor');
        $seller = $this->profile($sellerConversation, 40, 35, 'seller', $readyAuthorization ? [
            'authorization_control' => $this->readyAuthorization(),
            'private_seller_floor' => 2_100_000,
        ] : []);
        $investor = $this->profile($investorConversation, 40, 35, 'investor');
        $this->acceptedActivity($sellerConversation, $seller, $investor, 3_000_000);

        return [$seller, $investor];
    }

    private function caseState(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        array $overrides = [],
        bool $persist = false,
    ): array {
        $state = array_merge([
            'id' => $seller->id.':'.$investor->id,
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'status' => 'appointment_scheduled',
            'agreed_price' => 3_000_000,
            'title_deed_verified' => true,
            'identity_authority_verified' => true,
            'encumbrance_checked' => true,
            'tax_fee_checked' => true,
            'payment_method_confirmed' => true,
            'appointment_at' => now()->addDay()->toIso8601String(),
            'appointment_location' => 'Tapu Müdürlüğü',
            'deposit_amount' => 100_000,
            'deposit_received' => true,
            'final_payment_verified' => false,
            'deed_transfer_completed' => false,
            'operator_note' => 'PRIVATE OPERATOR NOTE',
            'updated_at' => now()->toIso8601String(),
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
        ], $overrides);

        if ($persist) {
            $data = is_array($seller->data) ? $seller->data : [];
            $data['transaction_closing_cases'] = [(string) $investor->id => $state];
            $seller->forceFill(['data' => $data])->saveQuietly();
        }

        return $state;
    }

    private function readyAuthorization(): array
    {
        return [
            'mandate_type' => 'exclusive',
            'signed_at' => now()->toDateString(),
            'expires_at' => now()->addMonths(3)->toDateString(),
            'seller_presentation_consent' => true,
            'commission_terms_acknowledged' => true,
            'title_owner_confirmed' => true,
            'authorization_document_present' => true,
            'legal_review_required' => false,
        ];
    }

    private function acceptedActivity(
        ConversationControl $conversation,
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        int $amount,
        int $user = 40,
        int $bot = 35,
    ): void {
        CrmActivity::query()->create([
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'conversation_control_id' => $conversation->id,
            'type' => 'real_estate_operator_call',
            'title' => 'Satıcı son teyidi',
            'description' => 'Gerçek teklif kabul edildi',
            'new_value' => 'Kabul etti',
            'meta' => [
                'scope' => 'isolated_real_estate',
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'task_kind' => 'seller_final',
                'outcome' => 'Kabul etti',
                'offer_amount' => $amount,
                'automatic_outbound_allowed' => false,
                'contains_private_seller_floor' => false,
            ],
        ]);
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

    private function conversation(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $name,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => '905550000000',
            'customer_name' => $name,
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedAccounts(): void
    {
        if (! User::query()->whereKey(40)->exists()) {
            $owner = User::query()->forceCreate([
                'id' => 40,
                'name' => 'Emlak AI',
                'email' => 'closing-risk@example.test',
                'password' => Hash::make('test'),
            ]);
            Organization::query()->forceCreate([
                'id' => 37,
                'owner_user_id' => 40,
                'name' => 'Emlak AI',
                'slug' => 'closing-risk',
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
        }

        if (! User::query()->whereKey(41)->exists()) {
            User::query()->forceCreate([
                'id' => 41,
                'name' => 'Foreign',
                'email' => 'foreign-closing-risk@example.test',
                'password' => Hash::make('test'),
            ]);
            Organization::query()->forceCreate([
                'id' => 38,
                'owner_user_id' => 41,
                'name' => 'Foreign',
                'slug' => 'foreign-closing-risk',
                'plan' => 'start',
                'seat_limit' => 1,
                'monthly_message_limit' => 1000,
                'status' => 'active',
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
                'whatsapp_instance' => 'foreign-closing-risk',
                'follow_up_enabled' => false,
                'second_follow_up_enabled' => false,
                'ai_enabled' => true,
            ]);
        }
    }
}
