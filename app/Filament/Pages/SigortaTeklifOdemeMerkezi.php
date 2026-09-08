<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Services\Insurance\InsuranceTenantContext;
use App\Services\Insurance\InsuranceWorkflowService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) $user?->is_admin || strtolower((string) $user?->email) === 'dogustopcu@gmail.com';
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    private function tenantCases()
    {
        return app(InsuranceTenantContext::class)->scopeCases(InsuranceCase::query());
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
        $q = app(InsuranceTenantContext::class)
            ->scopeCases(InsuranceCase::query())
            ->with(['quotes' => fn ($qq) => $qq->orderBy('premium'), 'assignedUser'])
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
                    ->orWhere('plate','like','%'.$term.'%');
            });
        }

        return $q->limit(100)->get();
    }

    public function moveToPayment(int $caseId): void
    {
        $case = app(InsuranceTenantContext::class)->case($caseId);
        app(InsuranceWorkflowService::class)->moveToPayment($case, auth()->user());
        Notification::make()->title('Dosya ödeme aşamasına taşındı')->success()->send();
    }

    public function markIssued(int $caseId): void
    {
        $case = app(InsuranceTenantContext::class)->case($caseId);
        app(InsuranceWorkflowService::class)->markIssued($case, auth()->user());
        Notification::make()->title('Poliçe tamamlandı')->success()->send();
    }

    public function bestQuote(InsuranceCase $case)
    {
        return $case->quotes->first(fn ($quote) => $quote->premium !== null);
    }
}
