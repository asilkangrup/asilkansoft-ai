@php
    $summary = $this->summary;
    $items = $this->items;
    $profiles = $this->profiles;
    $counts = (array) ($summary['counts'] ?? []);
    $bot = (array) ($summary['bot'] ?? []);
    $propertyLabel = static function ($profile): string {
        $data = is_array($profile?->data) ? $profile->data : [];

        return collect([
            data_get($data, 'city'),
            data_get($data, 'district'),
            data_get($data, 'neighborhood'),
            data_get($data, 'property_type'),
        ])->filter()->unique()->implode(' · ') ?: 'Taşınmaz';
    };
    $reasonLabel = static fn (string $reason): string => match ($reason) {
        'missing_price_ranges' => 'Fiyat aralığı yok',
        'insufficient_property_identity' => 'Taşınmaz kimliği yetersiz',
        'missing_profile_fingerprint' => 'Araştırma izi yok',
        'profile_changed' => 'Taşınmaz bilgisi değişti',
        'missing_researched_at' => 'Araştırma tarihi yok',
        'expired' => 'Araştırma süresi doldu',
        'missing_sources' => 'Kaynak yok',
        'missing_comparables' => 'Emsal yetersiz',
        'low_confidence' => 'Güven düşük',
        default => str_replace('_', ' ', $reason),
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 p-5 text-white shadow-sm sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-200">Operatör kontrollü araştırma</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Güncel Emsal ve Değerleme Kuyruğu</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-indigo-100">
                WhatsApp konuşması sırasında otomatik web araştırması kapalıdır. Yalnız sizin düğmeye basarak kuyruğa aldığınız dosya için bot 35'in kendi özel OpenAI anahtarıyla tek araştırma çalışır; sonuç CRM'e yazılır, müşteriye mesaj veya follow-up gönderilmez.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Araştırma bekleyen</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($counts['needs_research'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Yüksek öncelik</p>
                <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">{{ (int) ($counts['high_priority'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Kuyruk / çalışıyor</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ (int) ($counts['queued'] ?? 0) + (int) ($counts['running'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">İzole araştırma hattı</p>
                <p class="mt-1 text-sm font-bold {{ ($summary['ready'] ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                    {{ ($summary['ready'] ?? false) ? 'Hazır' : 'Kontrol gerekli' }}
                </p>
                <p class="mt-1 text-[11px] text-gray-500">
                    Özel anahtar {{ ($bot['dedicated_openai_key_configured'] ?? false) ? 'var' : 'yok' }} · Follow-up {{ ($bot['follow_ups_disabled'] ?? false) ? 'kapalı' : 'kontrol et' }}
                </p>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Operatör araştırma listesi</h2>
                    <p class="mt-1 text-xs text-gray-500">Sıralama: emsal bütünlüğü → güncellik → lead önceliği. Kimlik/doğrulama gibi daha güçlü bir blokaj varken ücretli araştırma düğmesi açılmaz.</p>
                </div>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Satıcı, şehir, ilçe veya tür"
                    class="w-full rounded-xl border-gray-300 text-sm sm:w-72 dark:border-white/10 dark:bg-gray-950"
                />
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($items as $item)
                    @php
                        $profile = $profiles->get((int) ($item['profile_id'] ?? 0));
                        $status = (string) ($item['request_status'] ?? '');
                        $busy = in_array($status, ['queued', 'running'], true);
                        $researchActionActive = (bool) ($item['research_action_active'] ?? false);
                        $canQueue = (bool) ($summary['ready'] ?? false) && ! $busy && $researchActionActive;
                    @endphp
                    <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-gray-950 dark:text-white">{{ $profile?->conversation?->customer_name ?: 'Satıcı dosyası' }}</h3>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">Öncelik {{ (int) ($item['priority_score'] ?? 0) }}</span>
                                    @if (($item['lead_temperature'] ?? '') === 'hot')
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">Sıcak lead</span>
                                    @endif
                                    @if ($busy)
                                        <span class="rounded-full bg-indigo-100 px-2 py-1 text-[11px] font-semibold text-indigo-800">{{ $status === 'running' ? 'Çalışıyor' : 'Kuyrukta' }}</span>
                                    @elseif ($status === 'failed')
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-[11px] font-semibold text-red-800">Başarısız</span>
                                    @elseif ($status === 'blocked')
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">Bloklu</span>
                                    @endif
                                    @if (! $researchActionActive)
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">Önce üst blokajı çöz</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $propertyLabel($profile) }}</p>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Güven: <strong>{{ (int) ($item['confidence_score'] ?? 0) }}/100</strong></span>
                                    <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Kaynak: <strong>{{ (int) ($item['source_count'] ?? 0) }}</strong></span>
                                    <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Emsal: <strong>{{ (int) ($item['comparable_count'] ?? 0) }}</strong></span>
                                    <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Lead: <strong>{{ (int) ($item['lead_score'] ?? 0) }}/100</strong></span>
                                </div>

                                @if (! empty($item['freshness_reasons']))
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach (array_slice((array) $item['freshness_reasons'], 0, 5) as $reason)
                                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">{{ $reasonLabel((string) $reason) }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                @if (filled($item['failure_code'] ?? null))
                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">Son çalışma kodu: {{ $item['failure_code'] }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 lg:w-64">
                                <button
                                    type="button"
                                    wire:click="queueResearch({{ (int) ($item['profile_id'] ?? 0) }})"
                                    wire:loading.attr="disabled"
                                    @disabled(! $canQueue)
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-45"
                                >
                                    {{ $busy ? 'Araştırma sırada' : ($researchActionActive ? 'Güncel emsal araştırmasını çalıştır' : 'Önce üst blokajı çöz') }}
                                </button>
                                <p class="text-[11px] leading-4 text-gray-500">Tek seferlik operatör işlemi. Otomatik tekrar, WhatsApp gönderimi veya müşteri takibi yoktur.</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center dark:border-white/15">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Araştırma bekleyen dosya yok.</p>
                        <p class="mt-1 text-xs text-gray-500">Mevcut değerlemeler eşleştirme için yeterli veya henüz araştırma gerektirecek nitelikte satıcı bulunmuyor.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs leading-5 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
            Güvenlik sınırı: yalnız user_id=40 / organization_id=37 / bot_id=35 / emlak-ai-35. Araştırma sonucundaki web metni CRM'e girmeden mevcut sanitizasyon katmanından geçer. Satıcının gizli alt fiyatı araştırma girdisine veya yatırımcı eşleştirme skoruna eklenmez. Follow-up özelliği bu panel tarafından açılamaz.
        </section>
    </div>
</x-filament-panels::page>
