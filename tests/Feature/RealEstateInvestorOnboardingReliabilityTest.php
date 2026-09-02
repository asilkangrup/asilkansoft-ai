<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealEstateInvestorOnboardingReliabilityTest extends TestCase
{
    public function test_investor_onboarding_collects_matching_criteria_before_completion(): void
    {
        $inbound = file_get_contents(
            app_path('Services/RealEstateWhatsAppInboundService.php')
        );
        $openAi = file_get_contents(
            app_path('Services/RealEstateOpenAIService.php')
        );

        $this->assertIsString($inbound);
        $this->assertIsString($openAi);

        foreach ([
            'property_type',
            'location',
            'investment_goal',
            'budget',
            'financing',
            'area_range',
            'risk_preference',
            'accepts_shared_title',
            'target_discount',
            'timeline',
        ] as $criterion) {
            $this->assertStringContainsString("'".$criterion."'", $inbound);
        }

        $this->assertStringContainsString(
            'deterministicInvestorOnboardingAnswer',
            $inbound
        );
        $this->assertStringContainsString(
            'investor_onboarding_intelligence',
            $inbound
        );
        $this->assertStringContainsString(
            'uygun fiyatlı gayrimenkulleri kriterlerinize göre eşleştirerek sunacağız',
            $inbound
        );
        $this->assertStringContainsString(
            'Yatırımcıdan elindeki ilanı/linki göndermesini ana kapanış',
            $openAi
        );
    }

    public function test_rate_limit_and_unanswered_recovery_are_self_contained(): void
    {
        $client = file_get_contents(
            app_path('Services/RealEstateOpenAIClient.php')
        );
        $batch = file_get_contents(
            app_path('Jobs/ProcessRealEstateMediaBatch.php')
        );
        $recovery = file_get_contents(
            app_path('Jobs/RecoverRealEstateUnansweredInbound.php')
        );
        $recoveryService = file_get_contents(
            app_path('Services/RealEstateInboundRecoveryService.php')
        );

        $this->assertIsString($client);
        $this->assertIsString($batch);
        $this->assertIsString($recovery);
        $this->assertIsString($recoveryService);

        $this->assertStringContainsString(
            "'real-estate-openai:bot:'",
            $client
        );
        $this->assertStringContainsString('->block(180', $client);
        $this->assertStringContainsString(
            'RecoverRealEstateUnansweredInbound::dispatch()',
            $batch
        );
        $this->assertStringContainsString(
            'a new conversation or creates a follow-up',
            strtolower($recovery)
        );
        $this->assertStringContainsString(
            "onQueue('real-estate')",
            $recovery
        );
        $this->assertStringContainsString(
            "->where('status', 'ignored')",
            $recoveryService
        );
        $this->assertStringContainsString(
            "'status' => 'failed'",
            $recoveryService
        );
        $this->assertStringContainsString(
            'no_saved_customer_message',
            $recoveryService
        );
        $this->assertStringContainsString(
            'public int $tries = 3',
            $recovery
        );
        $this->assertStringNotContainsString(
            "\$stats['failed']",
            substr($recovery, strpos($recovery, 'public function handle'))
        );
        $this->assertStringContainsString(
            'superseded_by_newer_customer_message',
            $recoveryService
        );
        $this->assertStringContainsString(
            'reply_already_exists',
            $recoveryService
        );
    }

    public function test_changes_remain_tenant_isolated_and_do_not_enable_followups(): void
    {
        $inbound = file_get_contents(
            app_path('Services/RealEstateWhatsAppInboundService.php')
        );
        $recovery = file_get_contents(
            app_path('Jobs/RecoverRealEstateUnansweredInbound.php')
        );

        $this->assertStringContainsString(
            'RealEstateProfile::query()',
            $inbound
        );
        $this->assertStringContainsString(
            '->isolatedProduction()',
            $inbound
        );
        $this->assertStringNotContainsString(
            'follow_up_enabled = true',
            $inbound.$recovery
        );
        $this->assertStringNotContainsString(
            'second_follow_up_enabled = true',
            $inbound.$recovery
        );

        $openAi = file_get_contents(
            app_path('Services/RealEstateOpenAIService.php')
        );
        $this->assertStringContainsString(
            "'max_output_tokens' => 500",
            $openAi
        );
        $this->assertStringContainsString(
            "'REAL_ESTATE_CHAT_REASONING_EFFORT',\n                    'low'",
            $openAi
        );
    }
}
