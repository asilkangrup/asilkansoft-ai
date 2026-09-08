<?php

namespace Tests\Feature;

use App\Filament\Pages\SigortaEkipMerkezi;
use App\Filament\Pages\SigortaOperasyonMerkezi;
use App\Filament\Pages\SigortaTeklifOdemeMerkezi;
use App\Filament\Pages\SigortaYenilemeMerkezi;
use App\Filament\Pages\SigortaYoneticiPaneli;
use App\Models\InsuranceCase;
use App\Models\InsuranceEvent;
use App\Models\InsuranceQuoteResult;
use App\Models\InsuranceRenewalOpportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Insurance\InsuranceWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InsuranceProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_insurance_pages_only_read_the_authenticated_insurance_tenant(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $orgA = $this->createInsuranceOrganization($ownerA, 'tenant-a');
        $orgB = $this->createInsuranceOrganization($ownerB, 'tenant-b');

        $caseA = InsuranceCase::create([
            'user_id' => $ownerA->id,
            'organization_id' => $orgA->id,
            'customer_name' => 'Tenant A Customer',
            'status' => 'quoted',
            'policy_type' => 'TRAFIK',
            'payment_status' => 'not_started',
        ]);
        $caseB = InsuranceCase::create([
            'user_id' => $ownerB->id,
            'organization_id' => $orgB->id,
            'customer_name' => 'Tenant B Customer',
            'status' => 'quoted',
            'policy_type' => 'TRAFIK',
            'payment_status' => 'not_started',
        ]);

        InsuranceRenewalOpportunity::create([
            'user_id' => $ownerA->id,
            'organization_id' => $orgA->id,
            'customer_name' => 'Renewal A',
            'status' => 'monitoring',
        ]);
        InsuranceRenewalOpportunity::create([
            'user_id' => $ownerB->id,
            'organization_id' => $orgB->id,
            'customer_name' => 'Renewal B',
            'status' => 'monitoring',
        ]);

        $this->actingAs($ownerA);

        $operation = app(SigortaOperasyonMerkezi::class);
        $operation->filter = 'all';
        $this->assertSame([$caseA->id], $operation->getCasesProperty()->pluck('id')->all());

        $quotePayment = app(SigortaTeklifOdemeMerkezi::class);
        $quotePayment->filter = 'all';
        $this->assertSame([$caseA->id], $quotePayment->getCasesProperty()->pluck('id')->all());

        $renewal = app(SigortaYenilemeMerkezi::class);
        $renewal->filter = 'all';
        $this->assertSame(['Renewal A'], $renewal->getRowsProperty()->pluck('customer_name')->all());

        $executive = app(SigortaYoneticiPaneli::class);
        $this->assertSame([$caseA->id], $executive->getRecentCasesProperty()->pluck('id')->all());

        $team = app(SigortaEkipMerkezi::class);
        $this->assertSame([$ownerA->id], $team->getMembersProperty()->pluck('id')->all());

        $this->assertTrue(SigortaOperasyonMerkezi::canAccess());
        $this->assertTrue(SigortaYenilemeMerkezi::canAccess());
        $this->assertTrue(SigortaEkipMerkezi::canAccess());
        $this->assertTrue(SigortaYoneticiPaneli::canAccess());
        $this->assertTrue(SigortaTeklifOdemeMerkezi::canAccess());

        try {
            $operation->selectCase($caseB->id);
            $this->fail('A foreign-tenant case was accessible.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_user_without_an_authorized_insurance_organization_fails_closed(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $this->assertFalse(SigortaOperasyonMerkezi::canAccess());

        $page = app(SigortaOperasyonMerkezi::class);
        $page->filter = 'all';

        $this->assertCount(0, $page->getCasesProperty());
        $this->assertSame(0, $page->getSummaryProperty()['active']);
    }

    public function test_insurance_owner_can_open_all_five_product_routes(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $this->createInsuranceOrganization($owner, 'route-access');

        $this->actingAs($owner);

        foreach ([
            '/admin/sigorta-operasyon',
            '/admin/sigorta-yenileme',
            '/admin/sigorta-ekip',
            '/admin/sigorta-yonetici',
            '/admin/sigorta-teklif-odeme',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/admin')->assertRedirect('/admin/sigorta-operasyon');
    }

    public function test_quote_payment_policy_flow_rejects_invalid_state_transitions(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createInsuranceOrganization($owner, 'workflow');
        $workflow = app(InsuranceWorkflowService::class);

        $case = InsuranceCase::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'customer_name' => '[TEST] Workflow',
            'status' => 'quoted',
            'policy_type' => 'TRAFIK',
            'payment_status' => 'not_started',
        ]);

        $quote = InsuranceQuoteResult::create([
            'insurance_case_id' => $case->id,
            'provider' => 'test',
            'company_name' => '[TEST] Provider',
            'premium' => 1250.50,
            'currency' => 'TRY',
            'status' => 'received',
        ]);

        $this->assertRuntimeFailure(
            fn () => $workflow->moveToPayment($case, $owner),
            'bir teklif seçilmelidir'
        );

        $workflow->selectQuote($case->refresh(), $quote, $owner);
        $workflow->moveToPayment($case->refresh(), $owner);

        $case->refresh();
        $this->assertSame('payment_ready', $case->status);
        $this->assertSame('ready', $case->payment_status);
        $this->assertSame($quote->id, $case->selected_quote_id);

        $this->assertRuntimeFailure(
            fn () => $workflow->markIssued($case->refresh(), $owner, 'POL-1'),
            'ödeme tamamlanmalıdır'
        );

        $this->assertRuntimeFailure(
            fn () => $workflow->markPaymentPaid($case->refresh(), 'bank_transfer', null, $owner),
            'işlem referansı zorunludur'
        );

        $workflow->markPaymentPaid($case->refresh(), 'bank_transfer', 'TEST-REF-1', $owner);

        $this->assertRuntimeFailure(
            fn () => $workflow->markIssued($case->refresh(), $owner, null),
            'Poliçe numarası zorunludur'
        );

        $workflow->markIssued($case->refresh(), $owner, 'TEST-POL-1');

        $case->refresh();
        $this->assertSame('issued', $case->status);
        $this->assertSame('paid', $case->payment_status);
        $this->assertSame('TEST-POL-1', $case->policy_number);
        $this->assertSame($owner->id, $case->closed_by_user_id);
        $this->assertSame(
            ['quote_selected', 'payment_ready', 'payment_paid', 'policy_issued'],
            InsuranceEvent::query()->where('insurance_case_id', $case->id)->orderBy('id')->pluck('type')->all()
        );
    }

    public function test_open_without_credentials_fails_closed_without_changing_case_state(): void
    {
        config([
            'insurance.open.acente_kodu' => null,
            'insurance.open.token' => null,
        ]);

        $owner = User::factory()->create();
        $organization = $this->createInsuranceOrganization($owner, 'open-guard');

        $case = InsuranceCase::create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'status' => 'ready_for_open',
            'policy_type' => 'TRAFIK',
            'open_teklif_id' => 123,
            'integration_status' => 'credentials_pending',
            'payment_status' => 'not_started',
        ]);

        $this->assertRuntimeFailure(
            fn () => app(InsuranceWorkflowService::class)->syncFromOpen($case, $owner),
            'Acente Kodu ve Token gerekli'
        );

        $case->refresh();
        $this->assertSame('ready_for_open', $case->status);
        $this->assertSame('credentials_pending', $case->integration_status);
        $this->assertNull($case->integration_error);
    }

    public function test_tpc_provisioning_creates_non_admin_owner_membership_and_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'dogustopcu@gmail.com',
            'is_admin' => true,
        ]);

        $migration = require database_path('migrations/2026_09_08_151500_provision_tpc_insurance_organization.php');
        $migration->up();

        $user->refresh();
        $organization = Organization::query()->where('slug', 'tpc-sigorta')->firstOrFail();
        $membership = DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertFalse($user->is_admin);
        $this->assertSame($user->id, $organization->owner_user_id);
        $this->assertSame('active', $organization->status);
        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertTrue((bool) data_get(json_decode($membership->permissions, true), 'insurance'));
    }

    private function createInsuranceOrganization(User $owner, string $slug): Organization
    {
        $organization = Organization::create([
            'owner_user_id' => $owner->id,
            'name' => 'Insurance '.$slug,
            'slug' => $slug,
            'status' => 'active',
            'settings' => [
                'product' => 'tpc_insurance_os',
                'insurance_only' => true,
            ],
        ]);

        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['insurance' => true]),
            'joined_at' => now(),
        ]);

        return $organization;
    }

    private function assertRuntimeFailure(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected workflow validation failure did not occur.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
