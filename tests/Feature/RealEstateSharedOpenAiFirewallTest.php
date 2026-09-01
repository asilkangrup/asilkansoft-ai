<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\CrmConversationSummaryService;
use App\Services\CrmManagerSummaryService;
use App\Services\FinanceLeadExtractorService;
use App\Services\RealEstateAwareCrmConversationSummaryService;
use App\Services\RealEstateAwareCrmManagerSummaryService;
use App\Services\RealEstateAwareFinanceLeadExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

class RealEstateSharedOpenAiFirewallTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_crm_summary_uses_structured_memory_without_shared_openai(): void
    {
        [$user, $bot] = $this->seedIsolatedTenant();
        $conversation = $this->sellerConversation($bot);

        RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'asking_price' => 5000000,
                'seller_minimum_price' => 3100000,
                'customer_phone' => '905551112233',
                'customer_email' => 'secret@example.test',
                'decision_intelligence' => [
                    'stage' => 'qualified',
                    'lead_temperature' => 'hot',
                    'lead_score' => 88,
                    'ready_for_match' => false,
                    'next_best_action' => 'Güncel emsal araştırmasını tamamla ve tapu/parsel doğrulamasını netleştir.',
                ],
                'verification_intelligence' => [
                    'status' => 'review',
                    'risk_score' => 35,
                    'safe_to_match' => false,
                    'next_best_action' => 'Belge desteğini güçlendir.',
                ],
            ],
            'valuation' => [],
            'completeness_score' => 85,
            'confidence_score' => 60,
        ]);

        foreach ([
            ['user', 'Arsamı satmak istiyorum.'],
            ['assistant', 'Konumu ve temel bilgileri netleştirelim.'],
            ['user', 'Marmaris, 1000 metrekare.'],
            ['assistant', 'Güncel emsallere göre araştıracağım.'],
        ] as [$role, $message]) {
            ChatMessage::query()->create([
                'user_id' => 40,
                'session_id' => $conversation->session_id,
                'role' => $role,
                'message' => $message,
            ]);
        }

        OpenAI::shouldReceive('responses')->never();

        $service = app(CrmConversationSummaryService::class);
        $this->assertInstanceOf(RealEstateAwareCrmConversationSummaryService::class, $service);

        $result = $service->updateIfNeeded($conversation, true);

        $this->assertTrue($result['updated']);
        $this->assertSame('real_estate_structured_summary', $result['reason']);
        $this->assertSame('real_estate_structured', $result['source']);
        $this->assertFalse($result['shared_openai_used']);

        $conversation->refresh();
        $this->assertStringContainsString('Marmaris', (string) $conversation->ai_summary);
        $this->assertStringContainsString('1000 m²', (string) $conversation->ai_summary);
        $this->assertStringNotContainsString('3100000', (string) $conversation->ai_summary);
        $this->assertStringNotContainsString('905551112233', (string) $conversation->ai_summary);
        $this->assertStringNotContainsString('secret@example.test', (string) $conversation->ai_summary);
        $this->assertNull($conversation->next_follow_up_at);
        $this->assertFalse((bool) $conversation->human_takeover);
        $this->assertSame(4, (int) $conversation->ai_summary_message_count);
    }

    public function test_isolated_manager_summary_is_deterministic_and_scoped_to_bot_35(): void
    {
        [$user, $bot] = $this->seedIsolatedTenant();
        $conversation = $this->sellerConversation($bot, 'manager-real-estate-session');
        $conversation->forceFill([
            'lead_temperature' => 'hot',
            'lead_score' => 90,
            'lead_status' => 'qualified',
            'estimated_value' => 4000000,
        ])->save();

        $otherBot = AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 40,
            'name' => 'Other User 40 Bot',
            'company_name' => 'Other',
            'business_sector' => 'ecommerce',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
        ]);
        $otherConversation = ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'foreign-bot-session',
            'lead_status' => 'qualified',
            'lead_temperature' => 'hot',
            'lead_score' => 99,
        ]);
        $otherConversation->forceFill(['organization_id' => 37])->saveQuietly();

        OpenAI::shouldReceive('responses')->never();

        $service = app(CrmManagerSummaryService::class);
        $this->assertInstanceOf(RealEstateAwareCrmManagerSummaryService::class, $service);

        $summary = $service->generate($user);

        $this->assertSame(1, (int) $summary->metrics['today_leads']);
        $this->assertSame(1, (int) $summary->metrics['hot_leads']);
        $this->assertSame(1, (int) $summary->metrics['priority_leads']);
        $this->assertSame(0, (int) $summary->metrics['overdue_follow_ups']);
        $this->assertStringContainsString('Emlak AI', (string) $summary->summary);
        $this->assertStringContainsString('4.000.000,00 TL', (string) $summary->summary);
    }

    public function test_isolated_finance_extractor_is_hard_blocked_even_if_group_routing_is_misconfigured(): void
    {
        [, $bot] = $this->seedIsolatedTenant();
        $bot->forceFill(['group_routing_enabled' => true])->save();

        OpenAI::shouldReceive('responses')->never();

        $service = app(FinanceLeadExtractorService::class);
        $this->assertInstanceOf(RealEstateAwareFinanceLeadExtractorService::class, $service);

        $result = $service->extract($bot, [[
            'role' => 'user',
            'content' => 'Adım Test, kredi başvurusu yapmak istiyorum.',
        ]]);

        $this->assertSame([
            'type' => null,
            'name' => null,
            'phone' => null,
            'city' => null,
            'line_owner' => null,
            'mother_maiden_surname' => null,
            'limit_score' => null,
            'birth_date' => null,
            'tc_identity_number' => null,
            'limit' => null,
        ], $result);
    }

    public function test_scope_drift_blocks_shared_crm_openai_instead_of_falling_back(): void
    {
        [, $bot] = $this->seedIsolatedTenant();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Wrong Organization',
            'slug' => 'wrong-organization',
            'status' => 'active',
        ]);
        $conversation = $this->sellerConversation($bot, 'scope-drift-session');

        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        OpenAI::shouldReceive('responses')->never();

        $result = app(CrmConversationSummaryService::class)
            ->updateIfNeeded($conversation, true);

        $this->assertFalse($result['updated']);
        $this->assertSame('real_estate_scope_mismatch_blocked', $result['reason']);
        $this->assertFalse($result['shared_openai_used']);
        $this->assertNull($conversation->fresh()->ai_summary);
    }

    private function seedIsolatedTenant(): array
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'emlak-firewall@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai',
            'status' => 'active',
        ]);

        $bot = AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'group_routing_enabled' => false,
            'ai_enabled' => true,
        ]);

        return [$user, $bot];
    }

    private function sellerConversation(
        AiBot $bot,
        string $session = 'crm-firewall-session'
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
            'whatsapp_number' => '905551112233',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
