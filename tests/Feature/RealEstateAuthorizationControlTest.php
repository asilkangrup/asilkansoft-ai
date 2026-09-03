<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakYetkilendirme;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateAuthorizationControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_seller_authorization_becomes_ready_and_is_audited_without_outbound(): void
    {
        $operator = $this->seedAccounts();
        $conversation = $this->conversation(40, 37, 35, 'authorization-seller');
        $profile = $this->profile($conversation, 40, 35, 'seller', [
            'minimum_price' => 3_100_000,
            'seller_private_note' => 'Yatırımcıya açıklanmayacak',
        ]);

        $conversation->forceFill(['next_follow_up_at' => null])->save();
        $result = app(RealEstateAuthorizationService::class)->update($profile, [
            'mandate_type' => 'exclusive',
            'signed_at' => now()->toDateString(),
            'expires_at' => now()->addMonths(3)->toDateString(),
            'seller_presentation_consent' => true,
            'commission_terms_acknowledged' => true,
            'title_owner_confirmed' => true,
            'authorization_document_present' => true,
            'legal_review_required' => false,
            'operator_note' => 'İmzalı nüsha kontrol edildi.',
        ], $operator);

        $this->assertSame('ready', $result['status']);
        $this->assertSame(2, $result['seller_commission_percent']);
        $this->assertSame(2, $result['buyer_commission_percent']);
        $this->assertFalse($result['automatic_outbound_allowed']);
        $this->assertFalse($result['customer_follow_up_allowed']);
        $this->assertFalse($result['legal_approval']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertDatabaseHas('crm_activities', [
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'type' => 'real_estate_authorization_control',
            'new_value' => 'ready',
        ]);

        $activity = $conversation->activities()->latest('id')->firstOrFail();
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_outbound_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'follow_up_scheduling_allowed'));

        $this->actingAs($operator);
        $page = new EmlakYetkilendirme;
        $page->selectedProfileId = $profile->id;
        $this->assertTrue(EmlakYetkilendirme::canAccess());
        $this->assertSame($profile->id, $page->getSelectedProfileProperty()?->id);
        $this->assertCount(1, $page->getProfilesProperty());
        $this->assertNotContains(false, $page->getChecklistProperty());
    }

    public function test_expired_or_legally_flagged_authorization_never_becomes_ready(): void
    {
        $operator = $this->seedAccounts();
        $conversation = $this->conversation(40, 37, 35, 'authorization-expired');
        $profile = $this->profile($conversation, 40, 35, 'seller');

        $expired = app(RealEstateAuthorizationService::class)->update($profile, [
            'mandate_type' => 'non_exclusive',
            'signed_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => now()->subDay()->toDateString(),
            'seller_presentation_consent' => true,
            'commission_terms_acknowledged' => true,
            'title_owner_confirmed' => true,
            'authorization_document_present' => true,
            'legal_review_required' => false,
            'operator_note' => null,
        ], $operator);

        $this->assertSame('expired', $expired['status']);

        $review = app(RealEstateAuthorizationService::class)->update($profile->fresh(), [
            'mandate_type' => 'exclusive',
            'signed_at' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'seller_presentation_consent' => true,
            'commission_terms_acknowledged' => true,
            'title_owner_confirmed' => true,
            'authorization_document_present' => true,
            'legal_review_required' => true,
            'operator_note' => 'Vekâlet incelenecek.',
        ], $operator);

        $this->assertSame('review_required', $review['status']);
        $this->assertCount(2, $review['history']);
    }

    public function test_foreign_and_investor_profiles_are_rejected_and_never_listed(): void
    {
        $operator = $this->seedAccounts();
        $foreignConversation = $this->conversation(41, 38, 36, 'foreign-authorization');
        $foreign = $this->profile($foreignConversation, 41, 36, 'seller');
        $investorConversation = $this->conversation(40, 37, 35, 'investor-authorization');
        $investor = $this->profile($investorConversation, 40, 35, 'investor');

        $this->assertSame([], app(RealEstateAuthorizationService::class)->summarize($foreign));
        $this->assertSame([], app(RealEstateAuthorizationService::class)->summarize($investor));

        $this->actingAs($operator);
        $this->assertCount(0, (new EmlakYetkilendirme)->getProfilesProperty());

        $this->expectException(HttpException::class);
        app(RealEstateAuthorizationService::class)->update($foreign, [
            'mandate_type' => 'none',
            'seller_presentation_consent' => false,
            'commission_terms_acknowledged' => false,
            'title_owner_confirmed' => false,
            'authorization_document_present' => false,
            'legal_review_required' => false,
        ], $operator);
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
            'whatsapp_number' => '905551112233',
            'customer_name' => 'Test Satıcı',
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
            'email' => 'authorization@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'authorization',
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
            'email' => 'foreign-authorization@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-authorization',
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
            'whatsapp_instance' => 'foreign-authorization',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $owner;
    }
}
