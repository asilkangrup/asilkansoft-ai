<?php

namespace App\Services;

use App\Observers\RealEstateProfileObserver;
use ReflectionClass;
use Throwable;

class RealEstateChatBoundaryGuardService
{
    public function snapshot(): array
    {
        $chatSource = $this->sourceFor(RealEstateOpenAIService::class);
        $clientSource = $this->sourceFor(RealEstateOpenAIClient::class);
        $valuationOutputGuardSource = $this->sourceFor(
            RealEstateValuationResearchOutputGuardService::class
        );
        $profileObserverSource = $this->sourceFor(RealEstateProfileObserver::class);

        $checks = [
            'chat_service_source_readable' => $chatSource !== null,
            'dedicated_client_source_readable' => $clientSource !== null,
            'dedicated_client_used' => $chatSource !== null
                && str_contains(
                    $chatSource,
                    'app(RealEstateOpenAIClient::class)'
                ),
            'direct_openai_client_absent' => $chatSource !== null
                && ! str_contains($chatSource, '\\OpenAI::client('),
            'customer_external_web_tool_absent' => $chatSource !== null
                && ! str_contains($chatSource, 'web_search_preview'),
            'trusted_internal_context_boundary_present' => $chatSource !== null
                && str_contains(
                    $chatSource,
                    'TRUSTED APPLICATION-GENERATED REAL ESTATE CONTEXT'
                ),
            'bot_level_key_required' => $clientSource !== null
                && str_contains($clientSource, '$aiBot->openai_api_key'),
            'global_openai_env_absent_from_dedicated_client' => $clientSource !== null
                && ! str_contains($clientSource, 'OPENAI_API_KEY'),
            'exact_isolation_guard_present' => $clientSource !== null
                && str_contains(
                    $clientSource,
                    'supportsBotIdentity($aiBot)'
                ),
            'valuation_research_output_guard_source_readable' =>
                $valuationOutputGuardSource !== null,
            'valuation_research_output_guard_wired_before_crm' =>
                $profileObserverSource !== null
                && str_contains(
                    $profileObserverSource,
                    'RealEstateValuationResearchOutputGuardService::class)->sanitize($profile)'
                ),
            'valuation_web_prose_removed_before_trusted_context' =>
                $valuationOutputGuardSource !== null
                && str_contains($valuationOutputGuardSource, "'summary' => null")
                && str_contains($valuationOutputGuardSource, "'next_best_action' => null"),
            'valuation_research_url_paths_not_promoted' =>
                $valuationOutputGuardSource !== null
                && str_contains($valuationOutputGuardSource, "'/r/'.\$token"),
            'valuation_research_scope_fail_closed' =>
                $valuationOutputGuardSource !== null
                && str_contains(
                    $valuationOutputGuardSource,
                    'RealEstateIsolationService::ORGANIZATION_ID'
                ),
        ];

        return [
            'ready' => ! in_array(false, $checks, true),
            'customer_external_tools_allowed' => false,
            'research_boundary' => 'structured_services_only',
            'valuation_research_output_boundary' => 'deterministic_redaction_before_crm',
            'dedicated_openai_key_only' => true,
            'follow_up_capability' => 'disabled',
            'checks' => $checks,
        ];
    }

    private function sourceFor(string $class): ?string
    {
        try {
            $reflection = new ReflectionClass($class);
            $file = $reflection->getFileName();

            if (! is_string($file) || $file === '' || ! is_readable($file)) {
                return null;
            }

            $source = file_get_contents($file);

            return is_string($source) ? $source : null;
        } catch (Throwable) {
            return null;
        }
    }
}
