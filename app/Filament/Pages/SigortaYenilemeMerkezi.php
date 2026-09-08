<?php

namespace App\Filament\Pages;

use App\Models\InsuranceRenewalOpportunity;
use App\Support\InsuranceTenantContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SigortaYenilemeMerkezi extends Page
{
    protected string $view = 'filament.pages.sigorta-yenileme-premium-v2';
    protected static ?string $title = 'Poliçe Yenileme & Geri Kazanım';
    protected static ?string $navigationLabel = 'Yenileme Merkezi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;
    protected static string|\UnitEnum|null $navigationGroup = 'Sigorta';
    protected static ?int $navigationSort = 21;
    protected static ?string $slug = 'sigorta-yenileme';

    public string $filter = 'active';
    public string $search = '';
    public string $customerName = '';
    public string $phone = '';
    public string $plate = '';
    public string $motorNumber = '';
    public string $policyNumber = '';
    public string $insurer = '';
    public string $expiryDate = '';

    public static function canAccess(): bool
    {
        return app(InsuranceTenantContext::class)->canUseInsurance();
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    private function tenant(): InsuranceTenantContext
    {
        return app(InsuranceTenantContext::class);
    }

    private function renewalQuery(): Builder
    {
        return $this->tenant()->scope(InsuranceRenewalOpportunity::query());
    }

    private function renewalOrFail(int $id): InsuranceRenewalOpportunity
    {
        return $this->renewalQuery()->findOrFail($id);
    }

    public function getSummaryProperty(): array
    {
        $q = $this->renewalQuery();
        return [
            'monitoring' => (clone $q)->where('status', 'monitoring')->count(),
            'detected' => (clone $q)->where('status', 'external_renewal_detected')->count(),
            'assigned' => (clone $q)->where('status', 'assigned')->count(),
            'recovered' => (clone $q)->where('status', 'recovered')->count(),
        ];
    }

    public function getRowsProperty(): Collection
    {
        $q = $this->renewalQuery()->latest('updated_at');
        if ($this->filter === 'active') $q->whereIn('status', ['monitoring', 'external_renewal_detected', 'assigned']);
        elseif ($this->filter !== 'all') $q->where('status', $this->filter);

        $term = trim($this->search);
        if ($term !== '') {
            $q->where(function ($sub) use ($term): void {
                $sub->where('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('plate', 'like', '%'.$term.'%')
                    ->orWhere('motor_number', 'like', '%'.$term.'%')
                    ->orWhere('policy_number', 'like', '%'.$term.'%');
            });
        }
        return $q->limit(150)->get();
    }

    public function createRecord(): void
    {
        $this->validate([
            'customerName' => ['nullable','string','max:255'],
            'phone' => ['nullable','string','max:32'],
            'plate' => ['nullable','string','max:32'],
            'motorNumber' => ['nullable','string','max:128'],
            'policyNumber' => ['nullable','string','max:128'],
            'insurer' => ['nullable','string','max:255'],
            'expiryDate' => ['nullable','date'],
        ]);

        $org = $this->tenant()->primaryOrganizationId();
        if (! auth()->user()?->is_admin && ! $org) {
            Notification::make()->title('Sigorta organizasyonu bulunamadı')->danger()->send();
            return;
        }

        InsuranceRenewalOpportunity::create([
            'user_id' => auth()->id(),
            'organization_id' => $org,
            'customer_name' => trim($this->customerName) ?: null,
            'phone' => trim($this->phone) ?: null,
            'plate' => strtoupper((string) preg_replace('/\s+/', '', trim($this->plate))) ?: null,
            'motor_number' => trim($this->motorNumber) ?: null,
            'policy_number' => trim($this->policyNumber) ?: null,
            'insurer' => trim($this->insurer) ?: null,
            'expiry_date' => $this->expiryDate ?: null,
            'status' => 'monitoring',
            'source' => 'panel',
        ]);

        $this->reset(['customerName','phone','plate','motorNumber','policyNumber','insurer','expiryDate']);
        Notification::make()->title('Yenileme takip kaydı oluşturuldu')->success()->send();
    }

    public function markDetected(int $id): void
    {
        $this->renewalOrFail($id)->update([
            'status' => 'external_renewal_detected',
            'external_policy_detected_at' => now(),
        ]);
        Notification::make()->title('Kayıt geri kazanım listesine alındı')->warning()->send();
    }

    public function assignToSales(int $id): void
    {
        $this->renewalOrFail($id)->update([
            'status' => 'assigned',
            'assigned_user_id' => auth()->id(),
        ]);
        Notification::make()->title('Satış ekibine aktarıldı')->success()->send();
    }

    public function markRecovered(int $id): void
    {
        $this->renewalOrFail($id)->update(['status' => 'recovered']);
        Notification::make()->title('Müşteri geri kazanıldı')->success()->send();
    }

    public function markLost(int $id): void
    {
        $this->renewalOrFail($id)->update(['status' => 'lost']);
        Notification::make()->title('Kayıt kaybedildi olarak kapatıldı')->warning()->send();
    }
}
