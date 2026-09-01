<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateEvidenceReconciliationService;
use App\Services\RealEstateVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateEvidenceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_confidence_media_safely_fills_blank_property_memory_with_provenance(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'asking_price' => 4_500_000,
            'urgency' => 'high',
            'media_findings' => [[
                'message_id' => 'media-safe-1',
                'document_type' => 'tapu',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'neighborhood' => 'Hisarönü',
                'area_sqm' => 1250,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'arsa',
                'zoning_status' => 'belgede açık değil',
                'visible_asking_price' => 4_200_000,
                'confidence_score' => 92,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        $result = app(RealEstateEvidenceReconciliationService::class)
            ->process($conversation);

        $profile->refresh();
        $data = $profile->data;

        $this->assertSame('Muğla', $data['city']);
        $this->assertSame('Marmaris', $data['district']);
        $this->assertSame('Hisarönü', $data['neighborhood']);
        $this->assertSame(1250.0, (float) $data['area_sqm']);
        $this->assertSame('101', $data['block_no']);
        $this->assertSame('22', $data['parcel_no']);
        $this->assertArrayNotHasKey('zoning_status', $data);
        $this->assertSame(4_500_000.0, (float) $data['asking_price']);
        $this->assertSame('whatsapp_media', $data['field_provenance']['city']['source']);
        $this->assertSame('media_observed_unverified', $data['field_provenance']['city']['status']);
        $this->assertSame('Muğla', $data['field_provenance']['city']['observed_value']);
        $this->assertFalse($data['field_provenance']['city']['officially_verified']);
        $this->assertContains('city', $result['promoted_fields']);
        $this->assertContains('asking_price', $result['conflict_fields']);
        $this->assertFalse($result['official_verification_complete']);
        $this->assertGreaterThan(0, $profile->completeness_score);
    }

    public function test_media_promoted_fields_cannot_verify_themselves(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'asking_price' => 4_500_000,
            'urgency' => 'high',
            'media_findings' => [[
                'message_id' => 'media-self-verify-1',
                'document_type' => 'tapu',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1250,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'arsa',
                'zoning_status' => 'konut',
                'confidence_score' => 94,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        app(RealEstateEvidenceReconciliationService::class)->process($conversation);
        $verification = app(RealEstateVerificationService::class)->process($conversation);

        $profile->refresh();

        $this->assertContains('city', $verification['media_only_fields']);
        $this->assertContains('district', $verification['media_only_fields']);
        $this->assertNotContains('city', $verification['corroborated_fields']);
        $this->assertNotContains('district', $verification['corroborated_fields']);
        $this->assertFalse($verification['safe_to_match']);
        $this->assertFalse($verification['legal_verification_complete']);
        $this->assertSame(
            $verification['media_only_fields'],
            $profile->data['verification_intelligence']['media_only_fields']
        );
    }

    public function test_customer_profile_matching_media_is_real_corroboration_not_media_self_verification(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'neighborhood' => 'Hisarönü',
            'area_sqm' => 1250,
            'block_no' => '101',
            'parcel_no' => '22',
            'title_deed_type' => 'arsa',
            'zoning_status' => 'konut',
            'asking_price' => 4_500_000,
            'urgency' => 'medium',
            'media_findings' => [[
                'message_id' => 'media-corroborate-1',
                'document_type' => 'tapu',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'neighborhood' => 'Hisarönü',
                'area_sqm' => 1250,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'arsa',
                'zoning_status' => 'konut',
                'visible_asking_price' => 4_500_000,
                'confidence_score' => 95,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        $evidence = app(RealEstateEvidenceReconciliationService::class)
            ->process($conversation);
        $verification = app(RealEstateVerificationService::class)
            ->process($conversation);

        $profile->refresh();

        $this->assertContains('city', $evidence['corroborated_fields']);
        $this->assertContains('district', $evidence['corroborated_fields']);
        $this->assertSame(
            'customer_profile',
            $profile->data['field_provenance']['city']['source']
        );
        $this->assertSame(
            'customer_profile_corroborated_by_media',
            $profile->data['field_provenance']['city']['status']
        );
        $this->assertContains('city', $verification['corroborated_fields']);
        $this->assertContains('district', $verification['corroborated_fields']);
        $this->assertNotContains('city', $verification['media_only_fields']);
        $this->assertTrue($verification['safe_to_match']);
        $this->assertFalse($verification['legal_verification_complete']);
    }

    public function test_existing_customer_value_is_never_overwritten_by_conflicting_media(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'property_type' => 'tarla',
            'city' => 'Aydın',
            'district' => 'Kuşadası',
            'area_sqm' => 900,
            'asking_price' => 3_900_000,
            'urgency' => 'medium',
            'media_findings' => [[
                'message_id' => 'media-conflict-1',
                'document_type' => 'ilan_ekran_goruntusu',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'area_sqm' => 1200,
                'visible_asking_price' => 4_700_000,
                'confidence_score' => 95,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        $result = app(RealEstateEvidenceReconciliationService::class)
            ->process($conversation);

        $profile->refresh();
        $data = $profile->data;

        $this->assertSame('Aydın', $data['city']);
        $this->assertSame('Kuşadası', $data['district']);
        $this->assertSame('tarla', $data['property_type']);
        $this->assertSame(900.0, (float) $data['area_sqm']);
        $this->assertSame(3_900_000.0, (float) $data['asking_price']);
        $this->assertContains('city', $result['conflict_fields']);
        $this->assertContains('district', $result['conflict_fields']);
        $this->assertContains('property_type', $result['conflict_fields']);
        $this->assertContains('area_sqm', $result['conflict_fields']);
        $this->assertContains('asking_price', $result['conflict_fields']);
        $this->assertGreaterThanOrEqual(5, $result['unresolved_conflict_count']);
    }

    public function test_reprocessing_same_media_does_not_ratchet_confidence(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'asking_price' => 4_000_000,
            'urgency' => 'medium',
            'media_findings' => [[
                'message_id' => 'media-idempotent-1',
                'document_type' => 'tapu',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'confidence_score' => 90,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        $service = app(RealEstateEvidenceReconciliationService::class);
        $first = $service->process($conversation);
        $profile->refresh();
        $afterFirst = $profile->confidence_score;

        $second = $service->process($conversation);
        $profile->refresh();

        $this->assertGreaterThan(25, $afterFirst);
        $this->assertSame($afterFirst, $profile->confidence_score);
        $this->assertSame(
            $first['confidence_boost_applied'],
            $second['confidence_boost_applied']
        );
        $this->assertContains('city', $second['repeated_media_fields']);
    }

    public function test_low_confidence_media_is_not_promoted_and_non_isolated_conversation_is_refused(): void
    {
        [$conversation, $profile] = $this->seedIsolatedProfile([
            'intent' => 'seller',
            'media_findings' => [[
                'message_id' => 'media-low-1',
                'document_type' => 'bulanık_gorsel',
                'city' => 'İzmir',
                'district' => 'Çeşme',
                'confidence_score' => 49,
                'analyzed_at' => now()->toIso8601String(),
            ]],
        ]);

        $result = app(RealEstateEvidenceReconciliationService::class)
            ->process($conversation);

        $profile->refresh();

        $this->assertSame([], $result['promoted_fields']);
        $this->assertArrayNotHasKey('city', $profile->data);
        $this->assertSame(0, $result['high_confidence_media_count']);

        $conversation->forceFill(['organization_id' => 999])->save();

        $this->assertNull(
            app(RealEstateEvidenceReconciliationService::class)
                ->process($conversation->fresh())
        );
        $this->assertNull(
            app(RealEstateVerificationService::class)
                ->process($conversation->fresh())
        );
    }

    private function seedIsolatedProfile(array $data): array
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'evidence@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-evidence',
            'status' => 'active',
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'evidence-session-'.bin2hex(random_bytes(4)),
            'whatsapp_number' => '905551112233',
            'channel' => 'whatsapp',
            'tags' => ['business:real_estate_seller'],
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 0,
            'confidence_score' => 25,
        ]);

        return [$conversation, $profile];
    }
}
