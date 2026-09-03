@php
    $deals = $this->deals;
    $selected = $this->selectedDeal;
    $commission = $this->commission;
    $totals = $this->totals;
    $money = static fn ($value) => number_format((float) $value, 0, ',', '.') . ' TL';
    $propertyLabel = static function ($profile): string {
        $data = is_array($profile?->data) ? $profile->data : [];

        return collect([
            data_get($data, 'city'),
            data_get($data, 'district'),
            data_get($data, 'location'),
            data_get($data, 'property_type') ?: data_get($data, 'property.type'),
        ])->filter()->unique()->implode(' · ') ?: 'Taşınmaz';
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-slate-950 p-5 text-white shadow-sm sm:p-7">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200">Emlak İş Merkezi</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Komisyon ve Tahsilat</h1>
                <p class="mt-2 text-sm leading-6 text-emerald-100">
                    Kabul edilmiş, kapanış kaydı açılmış işlemlerin satıcı %2 ve alıcı %2 hizmet bedelini güvenli biçimde takip edin.
                </p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Toplam hakediş</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $money($totals['total_due_amount'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tahsil edilen</p>
                <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ $money($totals['total_collected_amount'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Kalan</p>
                <p class="mt-1 text-lg font-bold text-amber-700 dark:text-amber-400">{{ $money($totals['total_remaining_amount'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tamamlanan</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ (int) ($totals['fully_collected_count'] ?? 0) }} / {{ (int) ($totals['deal_count'] ?? 0) }}</p>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100" for="commission-search">İşlem ara</label>
                <input
                    id="commission-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Satıcı, yatırımcı veya taşınmaz"
                    class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-gray-950"
                />

                <div class="mt-4 space-y-2">
                    @forelse ($deals as $deal)
                        @php
                            $dealCommission = (array) ($deal['commission'] ?? []);
                            $active = $selectedKey === $deal['key'];
                        @endphp
                        <button
                            type="button"
                            wire:key="commission-deal-{{ $deal['key'] }}"
                            wire:click="selectDeal('{{ $deal['key'] }}')"
                            class="w-full rounded-2xl border p-3 text-left transition {{ $active ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40' : 'border-gray-200 hover:border-emerald-300 dark:border-white/10' }}"
                        >
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $propertyLabel($deal['seller']) }}</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ $deal['seller']->conversation?->customer_name ?: 'Satıcı' }} · {{ $deal['investor']->conversation?->customer_name ?: 'Yatırımcı' }}
                            </span>
                            <span class="mt-2 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ ($dealCommission['status'] ?? 'awaiting') === 'collected' ? 'bg-emerald-100 text-emerald-800' : (($dealCommission['status'] ?? 'awaiting') === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                {{ $dealCommission['status_label'] ?? 'Tahsilat bekliyor' }}
                            </span>
                        </button>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
                            Kapanış kaydı bulunan kabul edilmiş işlem yok.
                        </div>
                    @endforelse
                </div>
            </aside>

            <main>
                @if ($selected)
                    @php
                        $closingStatus = (string) data_get($selected, 'closing_case.status', '');
                        $closingCompleted = $closingStatus === 'completed';
                    @endphp

                    <form wire:submit="save" class="space-y-5">
                        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Seçili işlem</p>
                                    <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $propertyLabel($selected['seller']) }}</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $selected['seller']->conversation?->customer_name ?: 'Satıcı' }} ↔ {{ $selected['investor']->conversation?->customer_name ?: 'Yatırımcı' }}
                                    </p>
                                </div>
                                <span class="inline-flex self-start rounded-full px-3 py-1 text-xs font-semibold {{ $closingCompleted ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $closingCompleted ? 'Kapanış tamamlandı' : 'Kapanış tamamlanmadı' }}
                                </span>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                    <p class="text-xs text-gray-500">Anlaşılan satış bedeli</p>
                                    <p class="mt-1 font-bold text-gray-950 dark:text-white">{{ $money($commission['agreed_price'] ?? 0) }}</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                    <p class="text-xs text-gray-500">Satıcı hizmet bedeli · %{{ $commission['seller_rate_percent'] ?? 2 }}</p>
                                    <p class="mt-1 font-bold text-gray-950 dark:text-white">{{ $money($commission['seller_due_amount'] ?? 0) }}</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-950">
                                    <p class="text-xs text-gray-500">Alıcı hizmet bedeli · %{{ $commission['buyer_rate_percent'] ?? 2 }}</p>
                                    <p class="mt-1 font-bold text-gray-950 dark:text-white">{{ $money($commission['buyer_due_amount'] ?? 0) }}</p>
                                </div>
                            </div>

                            @unless ($closingCompleted)
                                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                    Tahsilat tutarı ancak tapu devri ve nihai ödeme insan tarafından doğrulanıp kapanış tamamlandıktan sonra kaydedilebilir.
                                </div>
                            @endunless
                        </section>

                        <section class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                                <h3 class="font-bold text-gray-950 dark:text-white">Satıcı tahsilatı</h3>
                                <p class="mt-1 text-xs text-gray-500">Kalan: {{ $money($commission['seller_remaining_amount'] ?? 0) }}</p>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Tahsil edilen tutar
                                        <input type="number" min="0" step="1" wire:model="sellerCollectedAmount" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                        @error('sellerCollectedAmount') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Tahsilat tarihi
                                        <input type="date" wire:model="sellerCollectedAt" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                        @error('sellerCollectedAt') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>

                                <label class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Makbuz / belge referansı
                                    <input type="text" maxlength="150" wire:model="sellerReceiptReference" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                </label>
                            </div>

                            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                                <h3 class="font-bold text-gray-950 dark:text-white">Alıcı tahsilatı</h3>
                                <p class="mt-1 text-xs text-gray-500">Kalan: {{ $money($commission['buyer_remaining_amount'] ?? 0) }}</p>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Tahsil edilen tutar
                                        <input type="number" min="0" step="1" wire:model="buyerCollectedAmount" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                        @error('buyerCollectedAmount') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Tahsilat tarihi
                                        <input type="date" wire:model="buyerCollectedAt" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                        @error('buyerCollectedAt') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>

                                <label class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Makbuz / belge referansı
                                    <input type="text" maxlength="150" wire:model="buyerReceiptReference" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                                </label>
                            </div>
                        </section>

                        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                            <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100">
                                Operatör notu
                                <textarea wire:model="operatorNote" rows="3" maxlength="1000" class="mt-2 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" placeholder="Yalnız ekip içi not"></textarea>
                            </label>

                            <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-gray-950">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Toplam kalan: {{ $money($commission['total_remaining_amount'] ?? 0) }}</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        Kayıt, müşteri mesajı veya ödeme talebi göndermez. Vergi, KDV, fatura ve muhasebe kontrolleri ayrıca insan tarafından doğrulanır.
                                    </p>
                                </div>
                                <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                                    Kaydı güncelle
                                </button>
                            </div>
                        </section>
                    </form>
                @else
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-gray-900 dark:text-gray-400">
                        Komisyon takibi için kapanış kaydı açılmış bir işlem seçin.
                    </div>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>
