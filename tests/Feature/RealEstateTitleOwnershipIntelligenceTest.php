<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateSellerOfferPacketService;
use App\Services\RealEstateTitleOwnershipIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateTitleOwnershipIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_customer_title_owner_statements_are_classified_without_pii(): void
    {
        $bot = $this->seedIsolatedAccount();
        $cases = [
            'Tapu benim üzerime.' => 'seller',
            'Tapu eşimin üzerine.' => 'spouse',
            'Tapu babamın üzerine.' => 'relative',
            'Tapu şirketin üzerine.' => 'company',
            'Tapu arkadaşımın üzerine.' => 'other_person',
        ];

        foreach ($cases as $text => $expected) {
            $session = 'title-owner-'.str_replace('_', '-', $expected);
            $conversation = $this->conversation($bot, $session);
            $profile = $this->profileQuietly($conversation);
            $this->customerMessage($conversation, $text);

            $summary = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);

            $this->assertSame($expected, $summary['relation']);
            $this->assertSame('explicit_customer_statement', $summary['source']);
            $this->assertFalse($summary['confirmed_by_operator']);
            $this->assertFalse($summary['legal_verification']);
            $this->assertFalse($summary['confirmation_required']);
            $this->assertNull($summary['note']);
            $this->assertNull($conversation->fresh()->next_follow_up_at);
        }
    }

    public function test_question_is_ignored_and_unrelated_message_does_not_erase_known_owner(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-question');
        $profile = $this->profileQuietly($conversation);

        $this->customerMessage($conversation, 'Tapu kimin üzerine?');
        $this->assertSame([], app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile));

        $this->customerMessage($conversation, 'Tapu annemin üzerine.');
        $this->assertSame(
            'relative',
            app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile)['relation']
        );

        $this->customerMessage($conversation, 'Fotoğrafları da birazdan gönderiyorum.');
        $summary = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile->fresh());

        $this->assertSame('relative', $summary['relation']);
        $this->assertFalse($summary['confirmation_required']);
    }

    public function test_conflicting_owner_is_held_until_customer_repeats_or_explicitly_corrects(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-conflict');
        $profile = $this->profileQuietly($conversation);

        $this->customerMessage($conversation, 'Tapu babamın üzerine.');
        app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);

        $this->customerMessage($conversation, 'Tapu benim üzerime.');
        $pending = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile->fresh());

        $this->assertSame('relative', $pending['relation']);
        $this->assertTrue($pending['confirmation_required']);
        $this->assertSame('seller', $pending['pending_relation']);

        $this->customerMessage($conversation, 'Tapu benim üzerime.');
        $confirmed = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile->fresh());

        $this->assertSame('seller', $confirmed['relation']);
        $this->assertFalse($confirmed['confirmation_required']);
        $this->assertNull($confirmed['pending_relation']);

        $this->customerMessage($conversation, 'Aslında tapu eşimin üzerine.');
        $corrected = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile->fresh());

        $this->assertSame('spouse', $corrected['relation']);
        $this->assertFalse($corrected['confirmation_required']);
    }

    public function test_operator_confirmed_owner_cannot_be_overwritten_by_customer_extraction(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-operator');
        $profile = $this->profileQuietly($conversation, [
            'title_ownership' => [
                'relation' => 'seller',
                'note' => 'Operatör görüşmede teyit etti',
                'confirmed_by_operator' => true,
                'legal_verification' => false,
                'source' => 'operator',
            ],
        ]);
        $this->customerMessage($conversation, 'Tapu babamın üzerine.');

        $summary = app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);

        $this->assertSame('seller', $summary['relation']);
        $this->assertTrue($summary['confirmed_by_operator']);
        $this->assertSame('Operatör görüşmede teyit etti', $summary['note']);
    }

    public function test_seller_packet_knows_owner_relation_but_never_calls_it_legal_verification(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-packet');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 4_300_000,
            'block_no' => '123',
            'parcel_no' => '45',
            'zoning_status' => 'konut',
            'title_deed_type' => 'arsa',
            'media_findings' => [[
                'media_category' => 'property_photo',
                'confidence_score' => 90,
            ]],
        ]);
        $this->customerMessage($conversation, 'Tapu babamın üzerine.');
        app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);

        $packet = app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());

        $this->assertTrue($packet['criteria_presence']['title_owner_context']);
        $this->assertNotContains('title_owner_context', $packet['missing_supporting_context']);
        $this->assertSame('relative', $packet['title_ownership']['relation']);
        $this->assertFalse($packet['title_ownership']['legal_verification']);
        $this->assertTrue($packet['guardrails']['title_owner_relation_is_not_legal_verification']);
        $this->assertStringContainsString(
            '"relation":"relative"',
            app(RealEstateSellerOfferPacketService::class)->promptFor($conversation)
        );
    }

    public function test_unresolved_owner_conflict_is_requested_again_as_supporting_context(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-packet-conflict');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 4_300_000,
            'block_no' => '123',
            'parcel_no' => '45',
            'zoning_status' => 'konut',
            'title_deed_type' => 'arsa',
            'media_findings' => [[
                'media_category' => 'property_photo',
                'confidence_score' => 90,
            ]],
            'title_ownership' => [
                'relation' => 'relative',
                'source' => 'explicit_customer_statement',
                'confirmed_by_operator' => false,
                'legal_verification' => false,
            ],
        ]);
        $this->customerMessage($conversation, 'Tapu benim üzerime.');
        app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);

        $packet = app(RealEstateSellerOfferPacketService::class)->sync($profile->fresh());

        $this->assertFalse($packet['criteria_presence']['title_owner_context']);
        $this->assertContains('title_owner_context', $packet['missing_supporting_context']);
        $this->assertTrue($packet['title_ownership']['confirmation_required']);
        $this->assertSame('seller', $packet['title_ownership']['pending_relation']);
        $this->assertStringContainsString('Tapu şu anda', (string) $packet['recommended_next_request']);
    }

    public function test_profile_observer_wires_customer_owner_statement_into_crm_memory(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'title-owner-observer');
        $this->customerMessage($conversation, 'Tapu eşimin üzerine.');

        $profile = RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'asking_price' => 4_000_000,
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $profile->refresh();

        $this->assertSame('spouse', data_get($profile->data, 'title_ownership.relation'));
        $this->assertSame(
            'explicit_customer_statement',
            data_get($profile->data, 'title_ownership.source')
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_fails_closed(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'title-owner-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 'title-owner-foreign');
        DB::table('conversation_controls')->where('id', $conversation->id)->update([
            'organization_id' => 38,
        ]);
        $conversation->refresh();
        $profile = $this->profileQuietly($conversation);

        $this->assertSame([], app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile));
        $this->assertArrayNotHasKey('title_ownership', $profile->fresh()->data);
    }

    private function profileQuietly(
        ConversationControl $conversation,
        array $data = [],
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => 'seller',
                'data' => $data,
                'valuation' => [],
                'completeness_score' => 70,
                'confidence_score' => 70,
            ])
        );
    }

    private function customerMessage(ConversationControl $conversation, string $text): ChatMessage
    {
        return ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => $text,
            'message_type' => 'text',
            'status' => 'received',
        ]));
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'title-owner@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'title-owner-isolated',
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
