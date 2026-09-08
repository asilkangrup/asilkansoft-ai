<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Models\InsuranceQuoteResult;
use App\Services\Insurance\InsuranceWorkflowService;
use App\Support\InsuranceTenantContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

class SigortaTeklifOdemeMerkezi extends Page
{
    protected string $view = 'filament.pages.sigorta-teklif-odeme-merkezi';
    protected static ?string $title = 'Teklif & Ödeme Merkezi';
    protected static ?string $navigationLabel = 'Teklif & Ödeme';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static string|\UnitEnum|null $navigationGroup = 'Sigorta';
    protected static ?int $navigationSort = 24;
    protected static ?string $slug = 'sigorta-teklif-odeme';

    public string $filter = 'quoted';
    public string $search = '';
    public array $paymentMethods = [];
    public array $paymentReferences = [];
    public array $policyNumbers = [];

    public static function canAccess(): bool
    {
        return app(InsuranceTenantContext::class)->canUseInsurance();
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    private function tenant(): InsuranceTenantContext
    {
        return app(InsuranceTenantContext::class);
    }

    private function tenantCases()
    {
        return $this->tenant()->scope(InsuranceCase::query());
    }

    private function caseOrFail(int $caseId): InsuranceCase
    {
        return $this->tenantCases()->findOrFail($caseId);
    }

    public function getSummaryProperty(): array
    {
        $q = $this->tenantCases();
        return [
            'quoted' => (clone $q)->where('status','quoted')->count(),
            'payment' => (clone $q)->where('status','payment_ready')->count(),
            'issued_today' => (clone $q)->where('status','issued')->whereDate('updated_at',today())->count(),
            'attention' => (clone $q)->whereIn('status',['needs_attention','failed'])->count(),
        ];
    }

    public function getCasesProperty(): Collection
    {
        $q = $this->tenant()->scope(InsuranceCase::query())
            ->with([
                'quotes' => fn ($qq) => $qq->orderBy('premium'),
                'selectedQuote',
                'assignedUser',
            ])
            ->latest();

        if ($this->filter === 'quoted') $q->where('status','quoted');
        elseif ($this->filter === 'payment') $q->where('status','payment_ready');
        elseif ($this->filter === 'issued') $q->where('status','issued');
        elseif ($this->filter === 'attention') $q->whereIn('status',['needs_attention','failed']);
        else $q->whereIn('status',['quoted','payment_ready','issued','needs_attention','failed']);

        $term = trim($this->search);
        if ($term !== '') {
            $q->where(function ($sub) use ($term): void {
                $sub->where('customer_name','like','%'.$term.'%')
                    ->orWhere('phone','like','%'.$term.'%')
                    ->orWhere('plate','like','%'.$term.'%')
                    ->orWhere('policy_number','like','%'.$term.'%')
                    ->orWhere('payment_reference','like','%'.$term.'%');
            });
        }

        return $q->limit(100)->get();
    }

    public function selectQuote(int $caseId, int $quoteId): void
    {
        try {
            $case = $this->caseOrFail($caseId);
            $quote = InsuranceQuoteResult::query()
                ->where('insurance_case_id', $case->id)
                ->findOrFail($quoteId);

            app(InsuranceWorkflowService::class)->selectQuote($case, $quote, auth()->user());
            Notification::make()->title('Teklif seçildi')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Teklif seçilemedi')->body($e->getMessage())->danger()->send();
        }
    }
    public function moveToPayment(int $caseId): void
    {
        try {
            $case = $this->caseOrFail($caseId);
            app(InsuranceWorkflowService::class)->moveToPayment($case, auth()->user());
            Notification::make()->title('Dosya ödeme aşamasına taşındı')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Ödeme aşamasına geçilemedi')->body($e->getMessage())->danger()->send();
        }
    }
    public function markPaymentPaid(int $caseId): void
    {
        try {
            $case = $this->caseOrFail($caseId);
            $method = trim((string) ($this->paymentMethods[$caseId] ?? ''));
            $reference = trim((string) ($this->paymentReferences[$caseId] ?? ''));

            app(InsuranceWorkflowService::class)->markPaymentPaid(
                $case,
                $method !== '' ? $method : null,
                $reference !== '' ? $reference : null,
                auth()->user(),
            );

            Notification::make()->title('Ödeme tamamlandı olarak kaydedildi')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Ödeme kaydedilemedi')->body($e->getMessage())->danger()->send();
        }
    }
    public function markIssued(int $caseId): void
    {
        try {
            $case = $this->caseOrFail($caseId);
            $policyNumber = trim((string) ($this->policyNumbers[$caseId] ?? ''));

            app(InsuranceWorkflowService::class)->markIssued(
                $case,
                auth()->user(),
                $policyNumber !== '' ? $policyNumber : null,
            );

            Notification::make()->title('Poliçe tamamlandı')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Poliçe kapatılamadı')->body($e->getMessage())->danger()->send();
        }
    }
    public function bestQuote(InsuranceCase $case)
    {
        return $case->quotes->first(fn ($quote) => $quote->premium !== null);
    }
}
