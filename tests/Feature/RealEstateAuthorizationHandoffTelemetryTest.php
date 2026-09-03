<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RealEstateAuthorizationHandoffTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_handoff_telemetry_exposes_authorization_gate_without_customer_payload(): void
    {
        $exit = Artisan::call('real-estate:investor-handoffs', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"authorization_required": 0', $output);
        $this->assertStringContainsString('"authorization_required_before_handoff": 0', $output);
        $this->assertStringContainsString('"authorization_gate_enforced": true', $output);
        $this->assertStringContainsString('"contains_customer_pii": false', $output);
        $this->assertStringContainsString('"automatic_investor_outreach_allowed": false', $output);
        $this->assertStringContainsString('"automatic_customer_follow_up_allowed": false', $output);
    }
}
