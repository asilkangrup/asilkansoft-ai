@php
    $summary = $this->summary;
    $estimate = $this->estimate;
    $transactions = $this->transactions;
    $candidates = $this->candidates;
    $selected = $this->selectedProfile;
    $money = static fn ($value) => is_numeric($value) ? number_format((float) $value, 0, ',', '.') . ' TL' : '—';
    $number = static fn ($value) => is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '—';
    $propertyLabel = static function ($profile): string {
        $data = is_array($profile?->data) ? $profile->data : [];

        return collect([
            data_get($data, 'city'),
            data_get($data, 'district'),
            data_get($data, 'neighborhood'),
            data_get($data, 'property_type'),
        ])->filter()->unique()->implode(' · ') ?: 'Taşınmaz';
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-emerald-950 to-teal-900 p-5 text-white shadow-sm sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200">Şirket hafızası</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Gerçekleşmiş İşlemlerden Özel Değerleme</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100">
                Yalnız tapu devri ve nihai ödemesi insan tarafından doğrulanmış satışlar şirketinizin özel emsal verisine dönüşür. İlan fiyatı veya satıcının gizli tabanı öğrenme verisi değildir.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Doğrulanmış satış</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['verified_transactions'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">Kapanışı tamamlanmış</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Coğrafi kapsam</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['unique_districts'] ?? 0) }} ilçe</p>
                <p class="mt-1 text-[11px] text-gray-500">{{ (int) ($summary['unique_cities'] ?? 0) }} şehir</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Medyan gerçekleşen m²</p>
                <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ $money($summary['median_unit_price'] ?? null) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">Tüm doğrulanmış işlemler</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Değerleme kalibrasyonu</p>
                <p class="mt-1 text-xl font-bold text-indigo-700 dark:text-indigo-400">
                    @if (is_numeric(data_get($summary, 'calibration.mean_absolute_error_percent')))
                        %{{ number_format((float) data_get($summary, 'calibration.mean_absolute_error_percent'), 1, ',', '.') }}
                    @else
                        —
                    @endif
                </p>
                <p class="mt-1 text-[11px] text-gray-500">{{ (int) data_get($summary, 'calibration.sample_count', 0) }} ölçülebilir tahmin</p>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <label for="private-valuation-search" class="text-sm font-bold text-gray-950 dark:text-white">Dosya seç</label>
                <input
                    id="private-valuation-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Satıcı, şehir, ilçe veya tür"
                    class="mt-2 w-full rounded-xl border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950"
                />

                <div class="mt-4 max-h-[34rem] space-y-2 overflow-y-auto">
                    @forelse ($candidates as $candidate)
                        <button
                            type="button"
                            wire:key="private-valuation-profile-{{ $candidate->id }}"
                            wire:click="selectProfile({{ $candidate->id }})"
                            class="w-full rounded-2xl border p-3 text-left transition {{ $selectedProfileId === $candidate->id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40' : 'border-gray-200 hover:border-emerald-300 dark:border-white/10' }}"
                        >
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $candidate->conversation?->customer_name ?: 'Satıcı' }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ $propertyLabel($candidate) }}</span>
                        </button>
                    @empty
                        <p class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-white/15">Satıcı dosyası bulunmuyor.</p>
                    @endforelse
                </div>
            </aside>

            <main class="space-y-5">
                @if ($selected)
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Özel veri karşılaştırması</p>
                                <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $propertyLabel($selected) }}</h2>
                                <p class="mt-1 text-sm text-gray-500">{{ $selected->conversation?->customer_name ?: 'Satıcı dosyası' }}</p>
                            </div>
                            <span class="inline-flex self-start rounded-full px-3 py-1 text-xs font-semibold {{ ($estimate['status'] ?? '') === 'ready' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $estimate['status_label'] ?? 'Özel veri henüz yetersiz' }}
                            </span>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                <p class="text-xs text-gray-500">Benzer gerçekleşmiş satış</p>
                                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ (int) ($estimate['sample_count'] ?? 0) }}</p>
                                <p class="text-[11px] text-gray-500">Güçlü benzerlik: {{ (int) ($estimate['strong_sample_count'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                <p class="text-xs text-gray-500">Medyan gerçekleşen m²</p>
                                <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ $money($estimate['median_unit_price'] ?? null) }}</p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                <p class="text-xs text-gray-500">Veri güveni</p>
                                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $estimate['confidence_label'] ?? 'Yetersiz örnek' }}</p>
                                <p class="text-[11px] text-gray-500">{{ (int) ($estimate['confidence_score'] ?? 0) }}/100</p>
                            </div>
                        </div>

                        @if (($estimate['status'] ?? '') === 'ready')
                            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Gerçekleşmiş satış verisinden iç referans</p>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                    <div><span class="block text-xs text-gray-500">Alt</span><strong class="text-sm text-gray-950 dark:text-white">{{ $money($estimate['suggested_sale_min'] ?? null) }}</strong></div>
                                    <div><span class="block text-xs text-gray-500">Medyan</span><strong class="text-sm text-emerald-800 dark:text-emerald-300">{{ $money($estimate['suggested_sale_mid'] ?? null) }}</strong></div>
                                    <div><span class="block text-xs text-gray-500">Üst</span><strong class="text-sm text-gray-950 dark:text-white">{{ $money($estimate['suggested_sale_max'] ?? null) }}</strong></div>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                Güvenilir fiyat bandı için en az {{ (int) ($estimate['minimum_sample_count'] ?? 3) }} benzer ve doğrulanmış satış gerekir. Sistem örnek yetersizken fiyat uydurmaz.
                            </div>
                        @endif

                        <p class="mt-4 text-xs leading-5 text-gray-500">
                            Bu sonuç yalnız operatör karar desteğidir; müşteriye fiyat garantisi, otomatik pazarlık ankrajı veya WhatsApp mesajı olarak gönderilmez.
                        </p>
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <h3 class="font-bold text-gray-950 dark:text-white">Kullanılan anonim benzer satışlar</h3>
                        <div class="mt-4 space-y-3">
                            @forelse (collect($estimate['comparables'] ?? []) as $comparable)
                                <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                                {{ collect([$comparable['city'] ?? null, $comparable['district'] ?? null, $comparable['neighborhood'] ?? null])->filter()->implode(' · ') }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $comparable['property_type'] ?? 'Taşınmaz' }} · {{ $number($comparable['area_sqm'] ?? null) }} m²</p>
                                        </div>
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">{{ (int) ($comparable['similarity_score'] ?? 0) }}/100</span>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-gray-500">Gerçekleşen m²: <strong class="text-gray-800 dark:text-gray-200">{{ $money($comparable['actual_unit_price'] ?? null) }}</strong></span>
                                        <span class="text-gray-400">İnsan doğrulamalı</span>
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/15">
                                    Bu dosyaya benzeyen doğrulanmış satış henüz yok.
                                </div>
                            @endforelse
                        </div>
                    </section>
                @else
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-gray-900">
                        Özel veri karşılaştırması için bir satıcı dosyası seçin.
                    </div>
                @endif
            </main>
        </div>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div>
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Anonim şirket satış hafızası</h2>
                <p class="mt-1 text-xs text-gray-500">Müşteri adı, telefon, ada/parsel, gizli taban ve ham konuşma saklanmaz veya gösterilmez.</p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($transactions as $transaction)
                    <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">
                            {{ collect([$transaction['city'] ?? null, $transaction['district'] ?? null, $transaction['neighborhood'] ?? null])->filter()->implode(' · ') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">{{ $transaction['property_type'] ?? 'Taşınmaz' }} · {{ $number($transaction['area_sqm'] ?? null) }} m²</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-gray-50 p-2 dark:bg-gray-950">
                                <span class="block text-[11px] text-gray-500">Gerçekleşen satış</span>
                                <strong class="text-sm">{{ $money($transaction['actual_sale_price'] ?? null) }}</strong>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-2 dark:bg-gray-950">
                                <span class="block text-[11px] text-gray-500">Gerçekleşen m²</span>
                                <strong class="text-sm">{{ $money($transaction['actual_unit_price'] ?? null) }}</strong>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/15">
                        İlk doğrulanmış tapu kapanışı tamamlandığında özel satış hafızası otomatik oluşacak.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
