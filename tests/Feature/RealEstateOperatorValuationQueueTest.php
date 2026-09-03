<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakDegerlemeKuyrugu;
use App\Jobs\RunRealEstateOperatorValuationResearch;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOperatorValuationService;
use App\Services\RealEstateValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateOperatorValuationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_is_isolated_prioritized_and_privacy_safe(): void
    {
        [$owner] = $this->seedAccounts();
        $isolated = $this->seller(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'valuation-queue-target',
            name: 'Gizli Satıcı',
            phone: '905559999991',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'minimum_price' => 1_234_567,
                'seller_private_note' => 'Bu not dışarı çıkmayacak',
                'decision_intelligence' => [
                    'lead_score' => 92,
                    'lead_temperature' => 'hot',
                ],
                'next_best_action_intelligence' => [
                    'action_code' => 'repair_comparable_integrity',
                ],
            ],
        );
        $this->seller(
            user: 41,
            organization: 38,
            bot: 36,
            session: 'valuation-queue-foreign',
            name: 'Foreign Seller',
            phone: '905559999992',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'area_sqm' => 1000,
                'decision_intelligence' => ['lead_score' => 100, 'lead_temperature' => 'hot'],
                'next_best_action_intelligence' => ['action_code' => 'repair_comparable_integrity'],
            ],
        );

        $service = app(RealEstateOperatorValuationService::class);
        $items = $service->queueItems();
        $summary = $service->summary();

        $this->assertCount(1, $items);
        $this->assertSame($isolated->id, $items->first()['profile_id']);
        $this->assertSame('repair_comparable_integrity', $items->first()['action_code']);
        $this->assertGreaterThanOrEqual(392, $items->first()['priority_score']);
        $this->assertFalse($items->first()['automatic_outbound_allowed']);
        $this->assertFalse($items->first()['customer_follow_up_allowed']);
        $this->assertFalse($items->first()['contains_customer_pii']);
        $this->assertFalse($items->first()['contains_private_seller_floor']);
        $this->assertTrue($summary['ready']);
        $this->assertTrue($summary['bot']['dedicated_openai_key_configured']);
        $this->assertTrue($summary['bot']['follow_ups_disabled']);
        $this->assertFalse($summary['automatic_market_research_allowed']);
        $this->assertTrue($summary['operator_trigger_required']);

        $payload = json_encode([
            'summary' => $summary,
            'items' => $items->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Gizli Satıcı', $payload);
        $this->assertStringNotContainsString('905559999991', $payload);
        $this->assertStringNotContainsString('1234567', $payload);
        $this->assertStringNotContainsString('Bu not dışarı çıkmayacak', $payload);
        $this->assertStringNotContainsString('Foreign Seller', $payload);

        $this->actingAs($owner);
        $this->assertTrue(EmlakDegerlemeKuyrugu::canAccess());
        $this->assertSame(1, (new EmlakDegerlemeKuyrugu)->getItemsProperty()->count());
    }

    public function test_only_exact_operator_and_exact_seller_can_request_research(): void
    {
        [$owner, $foreignOwner] = $this->seedAccounts();
        $isolated = $this->seller(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'valuation-request-target',
            name: 'Target Seller',
            phone: '905559999993',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'area_sqm' => 800,
            ],
        );
        $foreign = $this->seller(
            user: 41,
            organization: 38,
            bot: 36,
            session: 'valuation-request-foreign',
            name: 'Foreign Seller',
            phone: '905559999994',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'area_sqm' => 900,
            ],
        );
        $service = app(RealEstateOperatorValuationService::class);

        $state = $service->request($isolated->id, $owner->id);
        $this->assertSame('queued', $state['status']);
        $this->assertFalse($state['automatic_outbound_allowed']);
        $this->assertFalse($state['customer_follow_up_allowed']);
        $this->assertNull($isolated->fresh()->conversation?->next_follow_up_at);

        try {
            $service->request($isolated->id, $foreignOwner->id);
            $this->fail('Foreign operator request should have failed closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->request($foreign->id, $owner->id);
    }

    public function test_run_restores_automatic_research_flag_after_provider_failure_and_sends_nothing(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'valuation-run-failure',
            name: 'Failure Seller',
            phone: '905559999995',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'block_no' => '12',
                'parcel_no' => '34',
                'decision_intelligence' => [
                    'lead_score' => 88,
                    'lead_temperature' => 'hot',
                ],
                'next_best_action_intelligence' => [
                    'action_code' => 'refresh_valuation_research',
                ],
            ],
        );
        $service = app(RealEstateOperatorValuationService::class);
        $service->request($profile->id, $owner->id);

        $previousPutenv = getenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED');
        $previousEnvExists = array_key_exists('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED', $_ENV);
        $previousEnv = $_ENV['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] ?? null;
        $previousServerExists = array_key_exists('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED', $_SERVER);
        $previousServer = $_SERVER['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] ?? null;

        putenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED=false');
        $_ENV['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] = 'false';
        $_SERVER['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] = 'false';

        $mock = Mockery::mock(RealEstateValuationService::class);
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($conversation, string $message): bool {
                $this->assertSame(40, (int) $conversation->user_id);
                $this->assertSame(37, (int) $conversation->organization_id);
                $this->assertSame(35, (int) $conversation->ai_bot_id);
                $this->assertStringContainsString('güncel değerleme', $message);
                $this->assertSame('true', getenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'));

                return true;
            })
            ->andThrow(new \RuntimeException('provider failure test'));
        $this->app->instance(RealEstateValuationService::class, $mock);

        try {
            $result = $service->run($profile->id, $owner->id);

            $this->assertSame('failed', $result['status']);
            $this->assertSame(
                'research_execution_failed:RuntimeException',
                $result['failure_code']
            );
            $this->assertSame('false', getenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'));
            $this->assertSame('false', $_ENV['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED']);
            $this->assertSame('false', $_SERVER['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED']);
            $this->assertSame(0, ChatMessage::query()->count());
            $this->assertSame(0, RealEstateOutboundDelivery::query()->count());

            $fresh = $profile->fresh();
            $state = data_get($fresh->data, 'operator_valuation_research', []);
            $this->assertSame('failed', $state['status']);
            $this->assertFalse($state['automatic_outbound_allowed']);
            $this->assertFalse($state['customer_follow_up_allowed']);

            $bot = AiBot::query()->findOrFail(35);
            $this->assertFalse((bool) $bot->follow_up_enabled);
            $this->assertFalse((bool) $bot->second_follow_up_enabled);
        } finally {
            if ($previousPutenv === false) {
                putenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED');
            } else {
                putenv('REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED='.$previousPutenv);
            }

            if ($previousEnvExists) {
                $_ENV['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] = $previousEnv;
            } else {
                unset($_ENV['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED']);
            }

            if ($previousServerExists) {
                $_SERVER['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED'] = $previousServer;
            } else {
                unset($_SERVER['REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED']);
            }
        }
    }

    public function test_raw_follow_up_drift_blocks_operator_research_before_any_model_call(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'valuation-followup-drift',
            name: 'Drift Seller',
            phone: '905559999996',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'area_sqm' => 1000,
                'block_no' => '1',
                'parcel_no' => '2',
            ],
        );
        $service = app(RealEstateOperatorValuationService::class);
        $service->request($profile->id, $owner->id);

        DB::table('ai_bots')->where('id', 35)->update(['follow_up_enabled' => true]);

        $mock = Mockery::mock(RealEstateValuationService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(RealEstateValuationService::class, $mock);

        $result = $service->run($profile->id, $owner->id);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('isolation_or_bot_guard_failed', $result['failure_code']);
        $this->assertSame(0, ChatMessage::query()->count());
        $this->assertSame(0, RealEstateOutboundDelivery::query()->count());
    }

    public function test_job_is_unique_per_profile_and_never_declares_retry_spend(): void
    {
        $job = new RunRealEstateOperatorValuationResearch(123, 40);

        $this->assertSame('isolated-real-estate-operator-valuation:123', $job->uniqueId());
        $this->assertSame(1, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame(600, $job->uniqueFor);
    }

    private function seller(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $name,
        string $phone,
        array $data,
    ): RealEstateProfile {
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => $phone,
            'customer_name' => $name,
            'notes' => 'Gizli konuşma notu',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => (int) data_get($data, 'decision_intelligence.lead_score', 0),
            'lead_temperature' => (string) data_get($data, 'decision_intelligence.lead_temperature', 'cold'),
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 70,
            'last_extracted_at' => now(),
        ]));
    }

    /** @return array{0:User,1:User} */
    private function seedAccounts(): array
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'valuation-queue@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'valuation-queue',
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
            'openai_api_key' => 'sk-proj-isolated-test-key',
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
            'email' => 'foreign-valuation-queue@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-valuation-queue',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $foreign->organizations()->syncWithoutDetaching([
            38 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
        ]);
        AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 41,
            'name' => 'Foreign',
            'company_name' => 'Foreign',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-foreign-test-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'foreign-valuation-queue',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return [$owner, $foreign];
    }
}
