<?php

namespace Tests\Feature;

use App\Services\RealEstateAiCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealEstateAiCostFirewallTest extends TestCase
{
    use RefreshDatabase;

    public function test_nixpacks_pins_only_real_estate_workloads_to_cost_efficient_model(): void
    {
        $config = file_get_contents(base_path('nixpacks.toml'));

        $this->assertIsString($config);

        foreach ([
            'REAL_ESTATE_OPENAI_MODEL',
            'REAL_ESTATE_EXTRACTOR_MODEL',
            'REAL_ESTATE_MEDIA_MODEL',
            'REAL_ESTATE_VALUATION_MODEL',
        ] as $variable) {
            $this->assertStringContainsString(
                $variable.' = "gpt-5-mini"',
                $config
            );
        }

        $this->assertStringContainsString(
            'REAL_ESTATE_CHAT_REASONING_EFFORT = "low"',
            $config
        );
        $this->assertStringNotContainsString('OPENAI_API_KEY =', $config);
        $this->assertStringNotContainsString('gulten-sirketi-1', $config);
    }

    public function test_cost_calculator_exposes_the_previous_gpt_5_4_cost_gap(): void
    {
        $service = app(RealEstateAiCostService::class);

        $gpt54 = $service->calculate(
            model: 'gpt-5.4-2026-03-05',
            inputTokens: 1_000_000,
            cachedInputTokens: 0,
            outputTokens: 100_000,
        );
        $mini = $service->calculate(
            model: 'gpt-5-mini-2025-08-07',
            inputTokens: 1_000_000,
            cachedInputTokens: 0,
            outputTokens: 100_000,
        );

        $this->assertSame(4.0, $gpt54);
        $this->assertSame(0.45, $mini);
        $this->assertGreaterThan(8, $gpt54 / $mini);
    }

    public function test_cost_command_is_aggregate_only_and_hard_scoped(): void
    {
        $serviceSource = file_get_contents(
            app_path('Services/RealEstateAiCostService.php')
        );

        $this->assertIsString($serviceSource);
        $this->assertStringContainsString(
            "->where('user_id', RealEstateIsolationService::USER_ID)",
            $serviceSource
        );
        $this->assertStringContainsString(
            "->where('ai_bot_id', RealEstateIsolationService::BOT_ID)",
            $serviceSource
        );
        $this->assertStringNotContainsString('ChatMessage', $serviceSource);
        $this->assertStringNotContainsString('ConversationControl', $serviceSource);

        $this->artisan('real-estate:ai-cost', ['--hours' => 1])
            ->assertExitCode(0);
    }
}
