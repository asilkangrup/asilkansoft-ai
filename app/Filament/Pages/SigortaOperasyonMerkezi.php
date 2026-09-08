<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Models\InsuranceEvent;
use App\Models\User;
use App\Services\Insurance\InsuranceWorkflowService;
use App\Services\Insurance\OpenHizliTeklifClient;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

class SigortaOperasyonMerkezi extends Page
{
    protected string $view = 'filament.pages.sigorta-operasyon-premium-v3';
    protected static ?string $title = 'Sigorta Operasyon Merkezi';
    protected static ?string $navigationLabel = 'Sigorta Operasyon';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|\UnitEnum|null $navigationGroup = 'Sigorta';
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'sigorta-operasyon';

    public string $filter = 'active';
    public string $search = '';
    public string $customerName = '';
    public string $phone = '';
    public string $policyType = 'TRAFIK';
    public string $plate = '';
    public string $motorNumber = '';
    public string $chassisNumber = '';
    public string $licenseNumber = '';
    public array $openTeklifIds = [];
    public array $operatorIds = [];
    public ?int $selectedCaseId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) $user?->is_admin || strtolower((string) $user?->email) === 'dogustopcu@gmail.com';
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }
    public function getOpenStatusProperty(): array { return app(OpenHizliTeklifClient::class)->connectionSummary(); }

    public function getSummaryProperty(): array
    {
        $base = InsuranceCase::query();
        return [
            'active' => (clone $base)->active()->count(),
            'waiting' => (clone $base)->whereIn('status', ['waiting_vehicle', 'ready_for_open', 'open_pending'])->count(),
            'quoted' => (clone $base)->where('status', 'quoted')->count(),
            'payment' => (clone $base)->where('status', 'payment_ready')->count(),
            'attention' => (clone $base)->whereIn('status', ['needs_attention', 'failed'])->count(),
            'issued_today' => (clone $base)->where('status', 'issued')->whereDate('updated_at', today())->count(),
        ];
    }

    public function getCasesProperty(): Collection
    {
        $query = InsuranceCase::query()
            ->with(['quotes' => fn ($q) => $q->orderBy('premium'), 'assignedUser'])
            ->latest();

        if ($this->filter === 'active') $query->active();
        elseif ($this->filter === 'quoted') $query->where('status', 'quoted');
        elseif ($this->filter === 'payment') $query->where('status', 'payment_ready');
        elseif ($this->filter === 'attention') $query->whereIn('status', ['needs_attention', 'failed']);
        elseif ($this->filter === 'issued') $query->where('status', 'issued');

        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function ($q) use ($term): void {
                $q->where('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('plate', 'like', '%'.$term.'%')
                    ->orWhere('motor_number', 'like', '%'.$term.'%')
                    ->orWhere('chassis_number', 'like', '%'.$term.'%');
            });
        }

        return $query->limit(100)->get();
    }

    public function getTeamMembersProperty(): Collection
    {
        $organizationIds = auth()->user()?->activeOrganizations()->pluck('organizations.id') ?? collect();

        if ($organizationIds->isEmpty()) return collect();

        return User::query()
            ->whereHas('organizations', fn ($q) => $q
                ->whereIn('organizations.id', $organizationIds)
                ->where('organization_user.status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function getSelectedCaseProperty(): ?InsuranceCase
    {
        if (! $this->selectedCaseId) return null;
        return InsuranceCase::query()
            ->with(['quotes' => fn ($q) => $q->orderBy('premium'), 'events', 'assignedUser'])
            ->find($this->selectedCaseId);
    }

    public function selectCase(int $caseId): void { $this->selectedCaseId = $caseId; }
    public function closeCaseDetail(): void { $this->selectedCaseId = null; }

    public function assignOperator(int $caseId): void
    {
        $case = InsuranceCase::findOrFail($caseId);
        $operatorId = $this->operatorIds[$caseId] ?? null;

        if (! is_numeric($operatorId)) {
            Notification::make()->title('Personel seçin')->warning()->send();
            return;
        }

        $operator = $this->teamMembers->firstWhere('id', (int) $operatorId);
        if (! $operator) {
            Notification::make()->title('Bu kullanıcı sigorta ekibinde değil')->danger()->send();
            return;
        }

        $case->forceFill(['assigned_user_id' => (int) $operatorId])->save();

        InsuranceEvent::create([
            'insurance_case_id' => $case->id,
            'type' => 'operator_assigned',
            'title' => 'Dosya personele atandı',
            'meta' => [
                'assigned_user_id' => (int) $operatorId,
                'assigned_user_name' => $operator->name,
                'assigned_by' => auth()->id(),
            ],
        ]);

        $this->selectedCaseId = $caseId;
        Notification::make()->title('Dosya personele atandı')->success()->send();
    }

    public function createOperation(): void
    {
        $this->validate([
            'customerName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'policyType' => ['required', 'in:TRAFIK,KASKO,TSS,DASK,KONUT,IMM'],
            'plate' => ['nullable', 'string', 'max:32'],
            'motorNumber' => ['nullable', 'string', 'max:128'],
            'chassisNumber' => ['nullable', 'string', 'max:128'],
            'licenseNumber' => ['nullable', 'string', 'max:64'],
        ]);

        $organizationId = auth()->user()?->activeOrganizations()->value('organizations.id');
        $case = app(InsuranceWorkflowService::class)->createCase([
            'organization_id' => $organizationId,
            'customer_name' => trim($this->customerName) ?: null,
            'phone' => trim($this->phone) ?: null,
            'policy_type' => $this->policyType,
            'plate' => trim($this->plate) ?: null,
            'motor_number' => trim($this->motorNumber) ?: null,
            'chassis_number' => trim($this->chassisNumber) ?: null,
            'license_number' => trim($this->licenseNumber) ?: null,
            'source_channel' => 'panel',
        ], auth()->user());

        $this->selectedCaseId = $case->id;
        $this->reset(['customerName', 'phone', 'plate', 'motorNumber', 'chassisNumber', 'licenseNumber']);
        $this->policyType = 'TRAFIK';
        Notification::make()->title('Yeni sigorta işlemi oluşturuldu')->success()->send();
    }

    public function saveOpenTeklifId(int $caseId): void
    {
        $case = InsuranceCase::findOrFail($caseId);
        $value = $this->openTeklifIds[$caseId] ?? null;
        if (! is_numeric($value) || (int) $value <= 0) {
            Notification::make()->title('Geçerli Open Teklif ID girin')->warning()->send();
            return;
        }
        app(InsuranceWorkflowService::class)->attachOpenTeklifId($case, (int) $value, auth()->user());
        Notification::make()->title('Open teklif kaydı bağlandı')->success()->send();
    }

    public function syncOpen(int $caseId): void
    {
        $case = InsuranceCase::findOrFail($caseId);
        try {
            app(InsuranceWorkflowService::class)->syncFromOpen($case, auth()->user());
            Notification::make()->title('Open teklif verileri güncellendi')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Open senkronizasyonu tamamlanamadı')->body($e->getMessage())->danger()->send();
        }
    }

    public function moveToPayment(int $caseId): void
    {
        $case = InsuranceCase::findOrFail($caseId);
        app(InsuranceWorkflowService::class)->moveToPayment($case, auth()->user());
        $this->selectedCaseId = $caseId;
        Notification::make()->title('İşlem ödeme aşamasına taşındı')->success()->send();
    }

    public function markIssued(int $caseId): void
    {
        $case = InsuranceCase::findOrFail($caseId);
        app(InsuranceWorkflowService::class)->markIssued($case, auth()->user());
        $this->selectedCaseId = $caseId;
        Notification::make()->title('Poliçe tamamlandı olarak işaretlendi')->success()->send();
    }

    public function bestPremium(InsuranceCase $case): ?float
    {
        $quote = $case->quotes->first(fn ($q) => $q->premium !== null);
        return $quote?->premium !== null ? (float) $quote->premium : null;
    }

    public function elapsedLabel(InsuranceCase $case): string
    {
        $minutes = $case->created_at?->diffInMinutes(now()) ?? 0;
        if ($minutes < 60) return $minutes.' dk';
        $hours = (int) floor($minutes / 60);
        if ($hours < 24) return $hours.' sa '.($minutes % 60).' dk';
        return (int) floor($hours / 24).' gün '.($hours % 24).' sa';
    }

    public function slaLabel(InsuranceCase $case): string
    {
        $minutes = $case->created_at?->diffInMinutes(now()) ?? 0;
        if ($case->status === 'issued') return 'Tamamlandı';
        if ($minutes < 3) return 'Hedef içinde';
        if ($minutes < 10) return 'Takip edilmeli';
        return 'Gecikiyor';
    }
}
