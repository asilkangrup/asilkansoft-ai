<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateMediaEvidenceObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_media_row_reconciles_existing_analysis_before_verification(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'media-observer-evidence@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'media-observer-evidence',
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
            'session_id' => 'observer-evidence-session',
            'whatsapp_number' => '905550004455',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'intent' => 'seller',
                'asking_price' => 4_500_000,
                'urgency' => 'high',
                'media_findings' => [[
                    'message_id' => 'observer-existing-media-1',
                    'document_type' => 'tapu',
                    'property_type' => 'arsa',
                    'city' => 'Muğla',
                    'district' => 'Marmaris',
                    'area_sqm' => 1250,
                    'block_no' => '101',
                    'parcel_no' => '22',
                    'title_deed_type' => 'arsa',
                    'confidence_score' => 93,
                    'analyzed_at' => now()->toIso8601String(),
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 25,
            'confidence_score' => 40,
        ]);

        ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '[Fotoğraf]',
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => 'tapu.jpg',
            'whatsapp_message_id' => 'observer-existing-media-1',
            'status' => 'received',
        ]);

        $profile->refresh();
        $conversation->refresh();

        $this->assertSame('Muğla', $profile->data['city']);
        $this->assertSame('Marmaris', $profile->data['district']);
        $this->assertSame('101', $profile->data['block_no']);
        $this->assertSame('22', $profile->data['parcel_no']);
        $this->assertSame(
            'whatsapp_media',
            $profile->data['field_provenance']['city']['source']
        );
        $this->assertContains(
            'city',
            $profile->data['verification_intelligence']['media_only_fields']
        );
        $this->assertFalse(
            $profile->data['verification_intelligence']['safe_to_match']
        );
        $this->assertFalse(
            $profile->data['verification_intelligence']['legal_verification_complete']
        );
        $this->assertNull($conversation->next_follow_up_at);
    }
}
