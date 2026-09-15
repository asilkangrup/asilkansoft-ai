<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $leads = $this->leads;
    @endphp

    <div class="space-y-5">
        <section class="rounded-3xl bg-gray-950 p-5 text-white shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-bold tracking-[.2em] text-amber-400">WAI LEAD CENTER</div>
                    <h1 class="mt-2 text-2xl font-black tracking-tight">İstanbul Emlak</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-300">İstanbul'daki emlak ofisleri otomatik olarak burada ayrılır. Yeni İstanbul emlak lead'leri eklendikçe bu liste de kendiliğinden güncellenir.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-center">
                    <div class="text-2xl font-black">{{ number_format($stats['total'], 0, ',', '.') }}</div>
                    <div class="mt-1 text-[10px] text-gray-300">toplam kayıt</div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><strong class="block text-xl">{{ $stats['ready'] }}</strong><span class="text-xs text-gray-500">Hazır</span></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><strong class="block text-xl">{{ $stats['verified'] }}</strong><span class="text-xs text-gray-500">WhatsApp</span></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><strong class="block text-xl">{{ $stats['opened'] }}</strong><span class="text-xs text-gray-500">Gönderildi</span></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><strong class="block text-xl">{{ $stats['replied'] }}</strong><span class="text-xs text-gray-500">Cevap / WAI</span></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"><strong class="block text-xl">İstanbul</strong><span class="text-xs text-amber-700 dark:text-amber-300">Sabit şehir filtresi</span></div>
        </section>

        <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <input
                wire:model.live.debounce.400ms="search"
                type="search"
                placeholder="Emlak ofisi veya telefon ara..."
                class="w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm outline-none ring-1 ring-gray-200 focus:ring-2 focus:ring-amber-400 dark:bg-gray-950 dark:ring-gray-700"
            >
        </div>

        <section class="grid gap-3 xl:grid-cols-2">
            @forelse ($leads as $lead)
                @php
                    $statusText = match ($lead->status) {
                        'opened' => 'İlk temas açıldı',
                        'replied' => 'Cevap verdi',
                        'ai_active' => 'WAI aktif',
                        default => 'Hazır',
                    };
                    $canOpen = ! $lead->contact_opened_at && $lead->whatsapp_status !== 'unavailable';
                @endphp

                <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-950 font-black text-amber-400">
                            {{ mb_strtoupper(mb_substr($lead->company_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-bold">{{ $lead->company_name }}</h3>
                            <a class="text-sm text-gray-500" href="tel:{{ $lead->phone_e164 }}">{{ $lead->phone_e164 }}</a>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $statusText }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-[10px] font-semibold">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">İstanbul</span>
                        <span class="rounded-full px-2.5 py-1 {{ $lead->whatsapp_status === 'verified' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                            {{ $lead->whatsapp_status === 'verified' ? 'WhatsApp doğrulandı' : 'WhatsApp '.$lead->whatsapp_status }}
                        </span>
                        @if ($lead->source)
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $lead->source }}</span>
                        @endif
                    </div>

                    <div class="mt-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-950">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">İlk mesaj</div>
                        <p class="mt-1 text-sm">{{ $lead->first_message_text ?: 'Merhaba kolay gelsin, '.$lead->company_name.' doğru mudur?' }}</p>
                    </div>

                    <div class="mt-4 flex gap-2">
                        @if ($canOpen)
                            <a href="{{ route('outreach-leads.whatsapp', $lead) }}" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white hover:bg-emerald-700">WhatsApp'ta Aç</a>
                        @else
                            <button disabled class="flex-1 cursor-not-allowed rounded-xl bg-gray-200 px-4 py-3 text-sm font-bold text-gray-500 dark:bg-gray-800">{{ $lead->whatsapp_status === 'unavailable' ? 'WhatsApp Kullanılamıyor' : 'Bu Lead Açıldı' }}</button>
                        @endif

                        @if ($lead->source_url)
                            <a href="{{ $lead->source_url }}" target="_blank" rel="noopener" class="rounded-xl border border-gray-200 px-4 py-3 text-center text-sm font-bold dark:border-gray-700">Kaynak</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <strong class="block">İstanbul emlak kaydı bulunamadı</strong>
                    <span class="mt-1 block text-sm text-gray-500">Yeni İstanbul emlak lead'leri geldikçe otomatik burada görünecek.</span>
                </div>
            @endforelse
        </section>

        @if ($stats['total'] > 500 && $search === '')
            <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Performans için ilk 500 kayıt gösteriliyor. Arama kutusuyla tüm kayıt havuzunda arama yapabilirsin.</div>
        @endif
    </div>
</x-filament-panels::page>
