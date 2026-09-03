@php
    $report = $this->report;
    $totals = (array) ($report['totals'] ?? []);
    $rows = collect($report['rows'] ?? []);
    $candidates = $this->candidates;
    $selected = $this->selectedProfile;
    $money = static fn ($value) => number_format((float) $value, 0, ',', '.') . ' TL';
    $personLabel = static fn ($profile) => $profile?->conversation?->customer_name
        ?: ($profile?->profile_type === 'seller' ? 'Satıcı' : 'Yatırımcı');
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-emerald-950 p-5 text-white shadow-sm sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200">Emlak İş Merkezi</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Reklam → Lead → Satış</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200">
                Hangi kaynağın nitelikli dosya, teklif, kapanış ve tahsilat ürettiğini tek ekranda izleyin. Rapor yalnız mevcut CRM kayıtlarını toplar; müşteri mesajı göndermez.
            </p>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Başlangıç
                        <input type="date" wire:model.live="from" class="mt-1 w-full rounded-xl border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950" />
                    </label>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Bitiş
                        <input type="date" wire:model.live="until" class="mt-1 w-full rounded-xl border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950" />
                    </label>
                </div>
                <div class="flex gap-2">
                    @foreach ([7, 30, 90] as $days)
                        <button type="button" wire:click="usePeriod({{ $days }})" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-emerald-400 dark:border-white/10 dark:text-gray-200">
                            {{ $days }} gün
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Lead</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($totals['leads'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">{{ (int) ($totals['seller_leads'] ?? 0) }} satıcı · {{ (int) ($totals['investor_leads'] ?? 0) }} yatırımcı</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Nitelikli</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ (int) ($totals['qualified_leads'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">%{{ number_format((float) ($totals['qualification_rate'] ?? 0), 1, ',', '.') }} dönüşüm</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Teklif aşaması</p>
                <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">{{ (int) ($totals['offered_leads'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">%{{ number_format((float) ($totals['offer_rate'] ?? 0), 1, ',', '.') }} lead→teklif</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Kapanan işlem</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ (int) ($totals['closed_deals'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">%{{ number_format((float) ($totals['closing_rate'] ?? 0), 1, ',', '.') }} lead→satış</p>
            </div>
            <div class="col-span-2 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:col-span-1 dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Tahsil edilen</p>
                <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ $money($totals['commission_collected_amount'] ?? 0) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">Hakediş: {{ $money($totals['commission_due_amount'] ?? 0) }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Kaynak ve kampanya performansı</h2>
                    <p class="text-xs text-gray-500">Dönüşümler lead’in CRM’e giriş tarihine göre gruplanır.</p>
                </div>
                @if (($totals['unattributed_leads'] ?? 0) > 0)
                    <span class="mt-2 self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 sm:mt-0">
                        {{ (int) $totals['unattributed_leads'] }} kampanyasız lead
                    </span>
                @endif
            </div>

            <div class="mt-4 hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500">
                            <th class="px-3 py-3">Kaynak / kampanya</th>
                            <th class="px-3 py-3 text-right">Lead</th>
                            <th class="px-3 py-3 text-right">Nitelikli</th>
                            <th class="px-3 py-3 text-right">Teklif</th>
                            <th class="px-3 py-3 text-right">Kabul</th>
                            <th class="px-3 py-3 text-right">Kapanış</th>
                            <th class="px-3 py-3 text-right">Tahsilat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-3">
                                    <span class="block font-semibold text-gray-950 dark:text-white">{{ $row['source'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $row['campaign'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $row['leads'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $row['qualified_leads'] }} <span class="text-xs text-gray-400">(%{{ number_format((float) $row['qualification_rate'], 1, ',', '.') }})</span></td>
                                <td class="px-3 py-3 text-right">{{ $row['offered_leads'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $row['accepted_deals'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-400">{{ $row['closed_deals'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $money($row['commission_collected_amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500">Seçili dönemde CRM lead’i bulunmuyor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 space-y-3 lg:hidden">
                @forelse ($rows as $row)
                    <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-gray-950 dark:text-white">{{ $row['source'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['campaign'] }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700">{{ $row['leads'] }} lead</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                            <div class="rounded-xl bg-gray-50 p-2 dark:bg-gray-950"><span class="block text-xs text-gray-500">Nitelikli</span><strong>{{ $row['qualified_leads'] }}</strong></div>
                            <div class="rounded-xl bg-gray-50 p-2 dark:bg-gray-950"><span class="block text-xs text-gray-500">Teklif</span><strong>{{ $row['offered_leads'] }}</strong></div>
                            <div class="rounded-xl bg-gray-50 p-2 dark:bg-gray-950"><span class="block text-xs text-gray-500">Kabul</span><strong>{{ $row['accepted_deals'] }}</strong></div>
                            <div class="rounded-xl bg-emerald-50 p-2 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"><span class="block text-xs">Satış</span><strong>{{ $row['closed_deals'] }}</strong></div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Tahsilat: <strong class="text-gray-800 dark:text-gray-200">{{ $money($row['commission_collected_amount']) }}</strong></p>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/15">
                        Seçili dönemde CRM lead’i bulunmuyor.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="font-bold text-gray-950 dark:text-white">Lead kaynağını eşleştir</h2>
                <p class="mt-1 text-xs text-gray-500">Eksik kampanya bilgisini CRM profiline ekleyin.</p>
                <div class="mt-4 max-h-96 space-y-2 overflow-y-auto">
                    @forelse ($candidates as $candidate)
                        <button
                            type="button"
                            wire:key="attribution-profile-{{ $candidate->id }}"
                            wire:click="selectProfile({{ $candidate->id }})"
                            class="w-full rounded-2xl border p-3 text-left {{ $selectedProfileId === $candidate->id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40' : 'border-gray-200 dark:border-white/10' }}"
                        >
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $personLabel($candidate) }}</span>
                            <span class="text-xs text-gray-500">{{ $candidate->profile_type === 'seller' ? 'Satıcı' : 'Yatırımcı' }} · {{ $candidate->created_at?->format('d.m.Y') }}</span>
                        </button>
                    @empty
                        <p class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500">Profil bulunmuyor.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                @if ($selected)
                    <form wire:submit="saveAttribution" class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Seçili CRM profili</p>
                            <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $personLabel($selected) }}</h3>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Kaynak
                                <select wire:model="source" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950">
                                    @foreach ($this->sources as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Kampanya
                                <input type="text" maxlength="120" wire:model="campaign" placeholder="Örn. Acil arsa satışı" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Reklam seti
                                <input type="text" maxlength="120" wire:model="adSet" placeholder="İsteğe bağlı" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Reklam / kreatif
                                <input type="text" maxlength="120" wire:model="creative" placeholder="İsteğe bağlı" class="mt-1 w-full rounded-xl border-gray-300 dark:border-white/10 dark:bg-gray-950" />
                            </label>
                        </div>

                        <div class="flex flex-col gap-3 rounded-2xl bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-gray-950">
                            <p class="text-xs leading-5 text-gray-500">
                                Bu işlem yalnız rapor etiketini kaydeder; WhatsApp mesajı, müşteri takibi veya reklam platformu işlemi oluşturmaz.
                            </p>
                            <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">
                                Kaynağı kaydet
                            </button>
                        </div>
                    </form>
                @else
                    <p class="py-10 text-center text-sm text-gray-500">Kaynak bilgisi düzenlemek için bir CRM profili seçin.</p>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
