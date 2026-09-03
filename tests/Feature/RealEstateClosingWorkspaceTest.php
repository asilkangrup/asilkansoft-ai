<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakIslemKapanis;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\CrmActivity;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateClosingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_authorized_pair_moves_through_guarded_closing_without_outbound(): void
    {
        $operator = $this->seedAccounts();
        $sellerConversation = $this->conversation(40, 37, 35, 'closing-seller', 'Satıcı');
        $investorConversation = $this->conversation(40, 37, 35, 'closing-investor', 'Yatırımcı');
        $seller = $this->profile($sellerConversation, 40, 35, 'seller', [
            'location' => 'Marmaris Hisarönü',
            'property_type' => 'arsa',
            'private_seller_floor' => 2_100_000,
            'authorization_control' => $this->readyAuthorization(),
        ]);
        $investor = $this->profile($investorConversation, 40, 35, 'investor');
        $this->acceptedActivity($sellerConversation, $seller, $investor, 3_000_000);

        $service = app(RealEstateClosingService::class);
        $this->assertCount(1, $service->acceptedDeals());

        $review = $service->save($seller, $investor, $this->payload([
            'final_payment_verified' => true,
            'deed_transfer_completed' => false,
        ]), $operator);

        $this->assertSame('completion_review', $review['status']);
        $this->assertNull($review['closed_at']);

        $completed = $service->save($seller, $investor, $this->payload([
            'final_payment_verified' => true,
            'deed_transfer_completed' => true,
        ]), $operator);
        $summary = $completed;
        $encoded = json_encode($summary, JSON_UNESCAPED_UNICODE);

        $this->assertSame('completed', $completed['status']);
        $this->assertNotNull($completed['closed_at']);
        $this->assertSame(3_000_000, $summary['agreed_price']);
        $this->assertFalse($summary['automatic_outbound_allowed']);
        $this->assertFalse($summary['customer_follow_up_allowed']);
        $this->assertFalse($summary['contains_private_seller_floor']);
        $this->assertFalse($summary['contains_customer_pii']);
        $this->assertStringNotContainsString('2100000', $encoded);
        $this->assertStringNotContainsString('905550000000', $encoded);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);

        $activity = CrmActivity::query()
            ->where('type', 'real_estate_closing_control')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('completed', data_get($activity->meta, 'status'));
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_outbound_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'follow_up_scheduling_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_private_seller_floor'));
        $this->assertFalse((bool) data_get($activity->meta, 'contains_customer_pii'));

        $this->actingAs($operator);
        $page = new EmlakIslemKapanis;
        $page->selectedKey = $seller->id.':'.$investor->id;
        $this->assertTrue(EmlakIslemKapanis::canAccess());
        $this->assertCount(1, $page->getDealsProperty());
        $this->assertSame($completed['id'], $page->getClosingCaseProperty()['id'] ?? null);
    }

    public function test_unaccepted_or_unauthorized_pair_cannot_open_closing_case(): void
    {
        $operator = $this->seedAccounts();
        $sellerConversation = $this->conversation(40, 37, 35, 'closing-blocked-seller', 'Satıcı');
        $investorConversation = $this->conversation(40, 37, 35, 'closing-blocked-investor', 'Yatırımcı');
        $seller = $this->profile($sellerConversation, 40, 35, 'seller');
        $investor = $this->profile($investorConversation, 40, 35, 'investor');
        $this->acceptedActivity($sellerConversation, $seller, $investor, 2_500_000);

        $this->expectException(HttpException::class);
        app(RealEstateClosingService::class)->save(
            $seller,
            $investor,
            $this->payload(),
            $operator,
        );
    }

    public function test_foreign_tenant_offer_and_closing_case_are_never_visible(): void
    {
        $operator = $this->seedAccounts();
        $foreignSellerConversation = $this->conversation(41, 38, 36, 'foreign-closing-seller', 'Foreign Seller');
        $foreignInvestorConversation = $this->conversation(41, 38, 36, 'foreign-closing-investor', 'Foreign Investor');
        $foreignSeller = $this->profile($foreignSellerConversation, 41, 36, 'seller', [
            'authorization_control' => $this->readyAuthorization(),
        ]);
        $foreignInvestor = $this->profile($foreignInvestorConversation, 41, 36, 'investor');
        $this->acceptedActivity($foreignSellerConversation, $foreignSeller, $foreignInvestor, 9_000_000, 41, 36);

        $foreignCase = [
            'id' => $foreignSeller->id.':'.$foreignInvestor->id,
            'user_id' => 41,
            'organization_id' => 38,
            'ai_bot_id' => 36,
            'seller_profile_id' => $foreignSeller->id,
            'investor_profile_id' => $foreignInvestor->id,
            'status' => 'completed',
            'agreed_price' => 9_000_000,
        ];
        $foreignData = is_array($foreignSeller->data) ? $foreignSeller->data : [];
        $foreignData['transaction_closing_cases'] = [
            (string) $foreignInvestor->id => $foreignCase,
        ];
        $foreignSeller->forceFill(['data' => $foreignData])->saveQuietly();

        $service = app(RealEstateClosingService::class);
        $this->assertCount(0, $service->acceptedDeals());
        $this->assertNull($service->caseForPair($foreignSeller->id, $foreignInvestor->id));
        $this->assertSame([], $service->summary($foreignCase));

        $this->actingAs($operator);
        $this->assertCount(0, (new EmlakIslemKapanis)->getDealsProperty());

        $this->expectException(HttpException::class);
        $service->save($foreignSeller, $foreignInvestor, $this->payload(), $operator);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agreed_price' => 3_000_000,
            'title_deed_verified' => true,
            'identity_authority_verified' => true,
            'encumbrance_checked' => true,
            'tax_fee_checked' => true,
            'payment_method_confirmed' => true,
            'appointment_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'appointment_location' => 'Marmaris Tapu Müdürlüğü',
            'deposit_amount' => 100_000,
            'deposit_received' => true,
            'final_payment_verified' => false,
            'deed_transfer_completed' => false,
            'operator_note' => 'Operatör kontrol kaydı',
        ], $overrides);
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

    private function seedAccounts(): User
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'closing@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'closing',
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

        User::query()->forceCreate([
            'id' => 41,
            'name' => 'Foreign',
            'email' => 'foreign-closing@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-closing',
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
            'whatsapp_instance' => 'foreign-closing',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $owner;
    }
}
