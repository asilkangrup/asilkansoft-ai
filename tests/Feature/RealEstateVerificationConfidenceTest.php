<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateVerificationConfidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_confidence_media_cannot_create_a_blocking_property_conflict(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'verification-confidence@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'verification-confidence',
            'status' => 'active',
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'business_sector' => 'real_estate',
            'status' => 'active',
            'ai_enabled' => true,
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);

        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'verification-confidence-session',
            'whatsapp_number' => '905550001122',
            'tags' => ['business:real_estate_seller'],
        ]);

        RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'intent' => 'seller',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'block_no' => '10',
                'parcel_no' => '20',
                'title_deed_type' => 'arsa',
                'zoning_status' => 'konut',
                'asking_price' => 4_000_000,
                'urgency' => 'medium',
                'media_findings' => [[
                    'message_id' => 'low-confidence-wrong-location',
                    'document_type' => 'bulanık ekran görüntüsü',
                    'city' => 'İzmir',
                    'district' => 'Çeşme',
                    'area_sqm' => 2000,
                    'confidence_score' => 42,
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 95,
            'confidence_score' => 80,
        ]);

        $verification = app(RealEstateVerificationService::class)
            ->process($conversation);

        $this->assertNotNull($verification);
        $this->assertSame(0, $verification['media_evidence_count']);
        $this->assertSame(1, $verification['low_confidence_media_count']);
        $this->assertSame(70, $verification['minimum_media_confidence']);
        $this->assertSame([], $verification['conflicts']);
        $this->assertNotSame('blocked', $verification['status']);
        $this->assertFalse($verification['safe_to_match']);
        $this->assertFalse($verification['legal_verification_complete']);
        $this->assertStringContainsString(
            'yeterince net okunamadı',
            $verification['next_best_action']
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }
}
