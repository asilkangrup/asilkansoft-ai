<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundSafetyEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOutboundSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class RealEstateOutboundSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_transaction_certainty_is_replaced_and_privacy_safe_audited(): void
    {
        [$conversation, $profile] = $this->seedProfile('seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'asking_price' => 4_500_000,
        ]);

        $original = 'Size kesin alıcı var, bu arsa garanti satılır.';
        $messageId = 'wamid-sensitive-outbound-1';

        $result = app(RealEstateOutboundSafetyService::class)->protect(
            conversation: $conversation,
            answer: $original,
            inboundMessageId: $messageId,
        );

        $this->assertTrue($result['replaced']);
        $this->assertContains('unsupported_transaction_certainty', $result['reasons']);
        $this->assertNotSame($original, $result['answer']);

        $event = RealEstateOutboundSafetyEvent::query()->sole();
        $this->assertSame(40, $event->user_id);
        $this->assertSame(37, $event->organization_id);
        $this->assertSame(35, $event->ai_bot_id);
        $this->assertSame($conversation->id, $event->conversation_control_id);
        $this->assertSame($profile->id, $event->real_estate_profile_id);
        $this->assertSame(hash('sha256', $messageId), $event->inbound_message_id_hash);
        $this->assertSame(hash('sha256', $original), $event->response_hash);
        $this->assertSame('replaced', $event->action);
        $this->assertSame('seller', $event->recipient_role);

        $serialized = json_encode($event->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($original, (string) $serialized);
        $this->assertStringNotContainsString($messageId, (string) $serialized);
        $this->assertStringNotContainsString('905551112233', (string) $serialized);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_safe_disclaimer_is_not_false_positive_blocked(): void
    {
        [$conversation] = $this->seedProfile('seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
        ]);

        $answer = 'Bu aşamada hazır alıcı olduğunu söyleyemem; satış garantisi veremem ve önce doğrulama gerekir.';

        $result = app(RealEstateOutboundSafetyService::class)->protect(
            conversation: $conversation,
            answer: $answer,
            inboundMessageId: 'wamid-safe-disclaimer-1',
        );

        $this->assertFalse($result['replaced']);
        $this->assertSame($answer, $result['answer']);
        $this->assertSame([], $result['reasons']);
        $this->assertDatabaseCount('real_estate_outbound_safety_events', 0);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_false_official_verification_claim_is_replaced(): void
    {
        [$conversation] = $this->seedProfile('seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
        ]);

        $result = app(RealEstateOutboundSafetyService::class)->protect(
            conversation: $conversation,
            answer: 'Tapu doğrulandı ve takyidat temiz, güvenle ilerleyebiliriz.',
            inboundMessageId: 'wamid-official-claim-1',
        );

        $this->assertTrue($result['replaced']);
        $this->assertContains('unsupported_official_verification', $result['reasons']);
        $this->assertSame(
            ['unsupported_official_verification'],
            RealEstateOutboundSafetyEvent::query()->sole()->reasons
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_investor_cannot_receive_matched_sellers_confidential_floor(): void
    {
        $this->seedScope();
        [$sellerConversation, $seller] = $this->createProfile('seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'asking_price' => 4_500_000,
            'minimum_price' => 3_900_000,
        ], 'seller-floor');

        [$investorConversation, $investor] = $this->createProfile('investor', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'budget_max' => 5_000_000,
        ], 'investor-floor');

        $investor->update([
            'data' => [
                ...$investor->data,
                'opportunity_matches' => [[
                    'candidate_role' => 'seller',
                    'candidate_profile_id' => $seller->id,
                    'seller_profile_id' => $seller->id,
                    'match_score' => 88,
                    'grade' => 'strong',
                ]],
            ],
        ]);

        $result = app(RealEstateOutboundSafetyService::class)->protect(
            conversation: $investorConversation,
            answer: 'Bu portföy için minimum rakam 3.900.000 TL seviyesinde.',
            inboundMessageId: 'wamid-floor-leak-1',
        );

        $this->assertTrue($result['replaced']);
        $this->assertSame(['confidential_seller_floor'], $result['reasons']);
        $this->assertStringNotContainsString('3.900.000', $result['answer']);
        $this->assertSame('investor', RealEstateOutboundSafetyEvent::query()->sole()->recipient_role);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_same_numeric_value_without_floor_context_is_not_treated_as_secret_leak(): void
    {
        $this->seedScope();
        [, $seller] = $this->createProfile('seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'minimum_price' => 3_900_000,
        ], 'seller-public-number');

        [$investorConversation, $investor] = $this->createProfile('investor', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'budget_max' => 5_000_000,
        ], 'investor-public-number');

        $investor->update([
            'data' => [
                ...$investor->data,
                'opportunity_matches' => [[
                    'candidate_role' => 'seller',
                    'candidate_profile_id' => $seller->id,
                    'seller_profile_id' => $seller->id,
                    'match_score' => 85,
                    'grade' => 'strong',
                ]],
            ],
        ]);

        $answer = 'Portföy için paylaşılabilir çalışma fiyatı 3.900.000 TL olarak değerlendirilebilir.';
        $result = app(RealEstateOutboundSafetyService::class)->protect(
            conversation: $investorConversation,
            answer: $answer,
            inboundMessageId: 'wamid-public-number-1',
        );

        $this->assertFalse($result['replaced']);
        $this->assertSame($answer, $result['answer']);
        $this->assertDatabaseCount('real_estate_outbound_safety_events', 0);
    }

    public function test_repeated_block_of_same_generated_answer_is_idempotent(): void
    {
        [$conversation] = $this->seedProfile('seller', ['city' => 'Muğla']);
        $service = app(RealEstateOutboundSafetyService::class);

        $first = $service->protect(
            conversation: $conversation,
            answer: 'Kesin teklif var ve satış garantisi sağlandı.',
            inboundMessageId: 'wamid-idempotent-safety-1',
        );
        $second = $service->protect(
            conversation: $conversation,
            answer: 'Kesin teklif var ve satış garantisi sağlandı.',
            inboundMessageId: 'wamid-idempotent-safety-1',
        );

        $this->assertTrue($first['replaced']);
        $this->assertTrue($second['replaced']);
        $this->assertDatabaseCount('real_estate_outbound_safety_events', 1);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_cross_organization_conversation_fails_closed(): void
    {
        $this->seedScope();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Organization',
            'slug' => 'foreign-outbound-safety',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $foreignConversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => 35,
            'session_id' => 'foreign-outbound-safety',
            'whatsapp_number' => '905559998877',
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outbound güvenlik kapsamı ihlali');

        try {
            app(RealEstateOutboundSafetyService::class)->protect(
                conversation: $foreignConversation,
                answer: 'Kesin alıcı var.',
                inboundMessageId: 'wamid-foreign-org-1',
            );
        } finally {
            $this->assertDatabaseCount('real_estate_outbound_safety_events', 0);
            $this->assertNull($foreignConversation->fresh()->next_follow_up_at);
        }
    }

    private function seedProfile(string $type, array $data): array
    {
        $this->seedScope();

        return $this->createProfile($type, $data, 'single');
    }

    private function seedScope(): void
    {
        if (! User::query()->whereKey(40)->exists()) {
            User::query()->forceCreate([
                'id' => 40,
                'name' => 'Emlak AI',
                'email' => 'outbound-safety-'.bin2hex(random_bytes(4)).'@example.test',
                'password' => Hash::make('test-password'),
            ]);
        }

        if (! Organization::query()->whereKey(37)->exists()) {
            Organization::query()->forceCreate([
                'id' => 37,
                'owner_user_id' => 40,
                'name' => 'Emlak AI',
                'slug' => 'outbound-safety-'.bin2hex(random_bytes(4)),
                'plan' => 'start',
                'seat_limit' => 1,
                'monthly_message_limit' => 1000,
                'status' => 'active',
            ]);
        }

        if (! AiBot::query()->whereKey(35)->exists()) {
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
                'whatsapp_status' => 'connecting',
                'group_routing_enabled' => false,
                'follow_up_enabled' => false,
                'second_follow_up_enabled' => false,
                'ai_enabled' => true,
            ]);
        }
    }

    private function createProfile(string $type, array $data, string $suffix): array
    {
        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'outbound-safety-'.$suffix.'-'.bin2hex(random_bytes(4)),
            'whatsapp_number' => '905551112233',
            'tags' => ['business:real_estate_'.$type],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 0,
            'confidence_score' => 80,
        ]);

        return [$conversation, $profile];
    }
}
