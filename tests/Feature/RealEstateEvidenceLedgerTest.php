<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateEvidenceEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateEvidenceLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateEvidenceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_media_findings_create_privacy_safe_documentary_lineage(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-ledger-documentary');

        $profile = $this->profile($conversation, [[
            'message_id' => 'wamid.secret-document-123',
            'filename' => 'tapu-asil-905551112233.pdf',
            'mime_type' => 'application/pdf',
            'document_type' => 'tapu senedi 0555 111 22 33 owner@example.test',
            'summary' => 'Asil Soylu Marmaris 101 ada 22 parsel.',
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'block_no' => '101',
            'parcel_no' => '22',
            'title_deed_type' => 'müstakil',
            'warnings' => ['Owner phone 0555 111 22 33'],
            'confidence_score' => 92,
        ]]);

        $event = RealEstateEvidenceEvent::query()->sole();
        $serialized = json_encode($event->toArray(), JSON_UNESCAPED_UNICODE);

        $this->assertSame(40, $event->user_id);
        $this->assertSame(37, $event->organization_id);
        $this->assertSame(35, $event->ai_bot_id);
        $this->assertSame($conversation->id, $event->conversation_control_id);
        $this->assertSame($profile->id, $event->real_estate_profile_id);
        $this->assertSame('document', $event->source_type);
        $this->assertSame('documentary', $event->provenance_class);
        $this->assertSame(92, $event->confidence_score);
        $this->assertSame(1, $event->warning_count);
        $this->assertContains('block_no', $event->field_keys);
        $this->assertContains('parcel_no', $event->field_keys);
        $this->assertContains('block_parcel', $event->identity_signals);
        $this->assertSame(hash('sha256', 'wamid.secret-document-123'), $event->message_id_hash);
        $this->assertNotSame('wamid.secret-document-123', $event->message_id_hash);
        $this->assertNotNull($event->content_fingerprint);
        $this->assertStringNotContainsString('wamid.secret-document-123', $serialized);
        $this->assertStringNotContainsString('tapu-asil-905551112233.pdf', $serialized);
        $this->assertStringNotContainsString('owner@example.test', $serialized);
        $this->assertStringNotContainsString('0555 111 22 33', $serialized);
        $this->assertStringNotContainsString('Marmaris', $serialized);
        $this->assertStringNotContainsString('Asil Soylu', $serialized);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_evidence_ledger_is_idempotent_for_the_same_whatsapp_message(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-ledger-idempotent');
        $finding = [
            'message_id' => 'wamid.same-message',
            'mime_type' => 'image/jpeg',
            'document_type' => 'ilan ekran görüntüsü',
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 900,
            'confidence_score' => 97,
        ];
        $profile = $this->profile($conversation, [$finding]);

        $profile->update([
            'data' => [
                ...$profile->data,
                'decision_intelligence' => ['lead_score' => 88],
            ],
        ]);

        app(RealEstateEvidenceLedgerService::class)->record(
            conversation: $conversation,
            profile: $profile->fresh(),
            analysis: $finding,
        );

        $this->assertSame(1, RealEstateEvidenceEvent::query()->count());
        $this->assertSame(
            'supporting',
            RealEstateEvidenceEvent::query()->value('provenance_class')
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_scope_drift_cannot_write_to_isolated_evidence_ledger(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-ledger-scope');
        $profile = $this->profile($conversation, []);

        $conversation->forceFill(['organization_id' => null])->save();

        $result = app(RealEstateEvidenceLedgerService::class)->record(
            conversation: $conversation->fresh(),
            profile: $profile,
            analysis: [
                'message_id' => 'wamid.should-not-write',
                'mime_type' => 'application/pdf',
                'document_type' => 'tapu senedi',
                'block_no' => '1',
                'parcel_no' => '2',
                'confidence_score' => 99,
            ],
        );

        $this->assertNull($result);
        $this->assertSame(0, RealEstateEvidenceEvent::query()->count());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_health_exposes_evidence_ledger_readiness_and_telemetry(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-ledger-health');
        $this->profile($conversation, [[
            'message_id' => 'wamid.health-evidence',
            'mime_type' => 'application/pdf',
            'document_type' => 'tapu senedi',
            'block_no' => '10',
            'parcel_no' => '20',
            'confidence_score' => 90,
        ]]);

        $response = $this->getJson('/api/real-estate/health');

        $response->assertOk()
            ->assertJsonPath('checks.evidence_ledger_ready', true)
            ->assertJsonPath('evidence_ledger_telemetry_24h.events', 1)
            ->assertJsonPath('evidence_ledger_telemetry_24h.documentary', 1)
            ->assertJsonPath('evidence_ledger_telemetry_24h.qualified_documentary', 1);

        $this->assertNotContains(
            'evidence_ledger_ready',
            $response->json('blocking_checks', [])
        );
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'evidence-ledger@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-evidence-ledger',
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
            'group_routing_enabled' => false,
            'ai_enabled' => true,
        ]);
    }

    private function conversation(AiBot $bot, string $sessionId): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '905551112233',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function profile(
        ConversationControl $conversation,
        array $findings
    ): RealEstateProfile {
        return RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => ['media_findings' => $findings],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);
    }
}
