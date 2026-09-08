<?php

namespace App\Services\Insurance;

use App\Models\InsuranceCase;
use App\Models\InsuranceEvent;
use App\Models\InsuranceQuoteResult;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class InsuranceWorkflowService
{
    public function __construct(private OpenHizliTeklifClient $openClient)
    {
    }

    public function createCase(array $data, ?User $actor = null): InsuranceCase
    {
        return DB::transaction(function () use ($data, $actor): InsuranceCase {
            $status = $this->hasMinimumVehicleData($data) ? 'ready_for_open' : 'waiting_vehicle';

            $case = InsuranceCase::create([
                'user_id' => $actor?->id,
                'organization_id' => $data['organization_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? $actor?->id,
                'source_channel' => $data['source_channel'] ?? 'manual',
                'source_reference' => $data['source_reference'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $status,
                'priority' => (int) ($data['priority'] ?? 50),
                'policy_type' => strtoupper((string) ($data['policy_type'] ?? 'TRAFIK')),
                'plate' => $this->cleanPlate($data['plate'] ?? null),
                'license_number' => $data['license_number'] ?? null,
                'motor_number' => $data['motor_number'] ?? null,
                'chassis_number' => $data['chassis_number'] ?? null,
                'vehicle_brand' => $data['vehicle_brand'] ?? null,
                'vehicle_model' => $data['vehicle_model'] ?? null,
                'vehicle_year' => $data['vehicle_year'] ?? null,
                'integration_status' => $this->openClient->configured() ? 'ready' : 'credentials_pending',
                'payment_status' => 'not_started',
                'data' => Arr::except($data, ['organization_id', 'assigned_user_id']),
            ]);

            $this->event($case, 'case_created', 'Sigorta işlemi oluşturuldu', null, $actor);

            return $case;
        });
    }

    public function attachOpenTeklifId(InsuranceCase $case, int $teklifId, ?User $actor = null): InsuranceCase
    {
        $case->forceFill([
            'open_teklif_id' => $teklifId,
            'integration_status' => $this->openClient->configured() ? 'ready' : 'credentials_pending',
        ])->save();

        $this->event($case, 'open_teklif_linked', 'Open teklif kaydı bağlandı', 'Teklif ID: '.$teklifId, $actor);

        return $case->refresh();
    }

    public function syncFromOpen(InsuranceCase $case, ?User $actor = null): InsuranceCase
    {
        if (! $case->open_teklif_id) {
            throw new \RuntimeException('Open Teklif ID olmadan senkronizasyon başlatılamaz.');
        }

        $case->forceFill([
            'status' => 'open_pending',
            'integration_status' => 'syncing',
            'integration_error' => null,
        ])->save();

        try {
            $summary = $this->openClient->getTeklif((int) $case->open_teklif_id);
            $detail = $this->openClient->getTeklifDetay((int) $case->open_teklif_id);
            $prices = $this->openClient->getTeklifFiyatlari((int) $case->open_teklif_id);

            DB::transaction(function () use ($case, $summary, $detail, $prices, $actor): void {
                $case->forceFill(['selected_quote_id' => null])->save();
                $case->quotes()->delete();

                foreach ($this->normalizePrices($prices) as $price) {
                    InsuranceQuoteResult::create([
                        'insurance_case_id' => $case->id,
                        'provider' => 'open_hizli_teklif',
                        'company_name' => $price['company_name'],
                        'premium' => $price['premium'],
                        'currency' => 'TRY',
                        'status' => 'received',
                        'payload' => $price['payload'],
                        'fetched_at' => now(),
                    ]);
                }

                $data = is_array($case->data) ? $case->data : [];
                $data['open'] = [
                    'summary' => $summary,
                    'detail' => $detail,
                    'prices_raw' => $prices,
                ];

                $case->forceFill([
                    'status' => 'quoted',
                    'integration_status' => 'synced',
                    'integration_error' => null,
                    'last_synced_at' => now(),
                    'payment_status' => 'not_started',
                    'data' => $data,
                ])->save();

                $this->event($case, 'open_sync_completed', 'Open teklif verileri güncellendi', null, $actor, [
                    'quote_count' => $case->quotes()->count(),
                ]);
            });
        } catch (Throwable $e) {
            $case->forceFill([
                'status' => 'needs_attention',
                'integration_status' => 'failed',
                'integration_error' => $e->getMessage(),
            ])->save();

            $this->event($case, 'open_sync_failed', 'Open senkronizasyonu başarısız', $e->getMessage(), $actor);
            throw $e;
        }

        return $case->refresh();
    }

    public function selectQuote(InsuranceCase $case, InsuranceQuoteResult $quote, ?User $actor = null): InsuranceCase
    {
        if ((int) $quote->insurance_case_id !== (int) $case->id) {
            throw new \RuntimeException('Seçilen teklif bu sigorta dosyasına ait değil.');
        }

        $case->forceFill(['selected_quote_id' => $quote->id])->save();

        $this->event($case, 'quote_selected', 'Teklif seçildi', null, $actor, [
            'quote_id' => $quote->id,
            'company_name' => $quote->company_name,
            'premium' => $quote->premium,
            'currency' => $quote->currency,
        ]);

        return $case->refresh();
    }

    public function moveToPayment(InsuranceCase $case, ?User $actor = null): InsuranceCase
    {
        if (! $case->selected_quote_id) {
            $bestQuote = $case->quotes()->whereNotNull('premium')->orderBy('premium')->first();
            if ($bestQuote) {
                $case->selected_quote_id = $bestQuote->id;
            }
        }

        $case->forceFill([
            'status' => 'payment_ready',
            'payment_status' => 'ready',
            'payment_ready_at' => now(),
        ])->save();

        $this->event($case, 'payment_ready', 'İşlem ödeme aşamasına taşındı', null, $actor, [
            'selected_quote_id' => $case->selected_quote_id,
        ]);

        return $case->refresh();
    }

    public function markPaymentPaid(
        InsuranceCase $case,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
        ?User $actor = null
    ): InsuranceCase {
        $case->forceFill([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod ?: $case->payment_method,
            'payment_reference' => $paymentReference ?: $case->payment_reference,
        ])->save();

        $this->event($case, 'payment_paid', 'Ödeme tamamlandı', null, $actor, [
            'payment_method' => $case->payment_method,
            'payment_reference' => $case->payment_reference,
        ]);

        return $case->refresh();
    }

    public function markIssued(
        InsuranceCase $case,
        ?User $actor = null,
        ?string $policyNumber = null
    ): InsuranceCase {
        $case->forceFill([
            'status' => 'issued',
            'payment_status' => $case->payment_status === 'not_started' ? 'ready' : $case->payment_status,
            'policy_number' => $policyNumber ?: $case->policy_number,
            'issued_at' => now(),
            'closed_by_user_id' => $actor?->id,
        ])->save();

        $this->event($case, 'policy_issued', 'Poliçe tamamlandı', null, $actor, [
            'policy_number' => $case->policy_number,
            'selected_quote_id' => $case->selected_quote_id,
        ]);

        return $case->refresh();
    }

    private function normalizePrices(array $payload): array
    {
        $rows = array_is_list($payload) ? $payload : ($payload['data'] ?? $payload['result'] ?? []);
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $company = $row['sirket'] ?? $row['sirketAdi'] ?? $row['firma'] ?? $row['companyName'] ?? null;
                $premium = $row['fiyat'] ?? $row['prim'] ?? $row['netPrim'] ?? $row['brutPrim'] ?? null;

                return [
                    'company_name' => is_scalar($company) ? (string) $company : null,
                    'premium' => is_numeric($premium) ? (float) $premium : null,
                    'payload' => $row,
                ];
            })
            ->values()
            ->all();
    }

    private function hasMinimumVehicleData(array $data): bool
    {
        return filled($data['plate'] ?? null)
            || filled($data['motor_number'] ?? null)
            || filled($data['chassis_number'] ?? null)
            || filled($data['license_number'] ?? null);
    }

    private function cleanPlate(mixed $plate): ?string
    {
        if (! is_scalar($plate) || trim((string) $plate) === '') {
            return null;
        }

        return strtoupper((string) preg_replace('/\s+/', '', (string) $plate));
    }

    private function event(
        InsuranceCase $case,
        string $type,
        string $title,
        ?string $description = null,
        ?User $actor = null,
        array $meta = []
    ): void {
        InsuranceEvent::create([
            'insurance_case_id' => $case->id,
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
