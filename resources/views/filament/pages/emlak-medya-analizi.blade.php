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
    $mediaLabel = static fn (array $item): string => ($item['media_type'] ?? '') === 'document'
        ? 'PDF / belge'
        : 'Fotoğraf';
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-5 text-white shadow-sm sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Operatör kontrollü medya zekâsı</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Fotoğraf ve Belge Analiz Kuyruğu</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-cyan-100">
                WhatsApp’tan gelen fotoğraf ve PDF’ler CRM’e otomatik kaydolur fakat kendiliğinden vision/OCR çalışmaz. Yalnız sizin seçtiğiniz medya, bot 35’in kendi özel OpenAI anahtarıyla bir kez analiz edilir. Sonuç CRM hafızasına ve doğrulama kararlarına işlenir; müşteriye otomatik mesaj veya follow-up gönderilmez.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Analiz bekleyen</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($counts['needs_analysis'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Fotoğraf / belge</p>
                <p class="mt-1 text-2xl font-bold text-cyan-700 dark:text-cyan-400">{{ (int) ($counts['images'] ?? 0) }} / {{ (int) ($counts['documents'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Kuyruk / çalışıyor</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700 dark:text-indigo-400">{{ (int) ($counts['queued'] ?? 0) + (int) ($counts['running'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500">İzole analiz hattı</p>
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
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">İncelenecek medya</h2>
                    <p class="mt-1 text-xs text-gray-500">PDF/belgeler ve sıcak lead medyaları önceliklidir. Aynı medyaya eşzamanlı ikinci ücretli çalışma açılamaz.</p>
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
                        $status = (string) ($item['analysis_status'] ?? '');
                        $busy = in_array($status, ['queued', 'running'], true);
                        $canQueue = (bool) ($summary['ready'] ?? false) && ! $busy && ! (bool) ($item['already_analyzed'] ?? false);
                    @endphp
                    <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-gray-950 dark:text-white">{{ $profile?->conversation?->customer_name ?: 'Satıcı dosyası' }}</h3>
                                    <span class="rounded-full bg-cyan-50 px-2 py-1 text-[11px] font-semibold text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-300">{{ $mediaLabel($item) }}</span>
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
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $propertyLabel($profile) }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Lead: <strong>{{ (int) ($item['lead_score'] ?? 0) }}/100</strong></span>
                                    @if (filled($item['mime_type'] ?? null))
                                        <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-950">Tür: <strong>{{ $item['mime_type'] }}</strong></span>
                                    @endif
                                </div>
                                @if (filled($item['failure_code'] ?? null))
                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">Son çalışma kodu: {{ $item['failure_code'] }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 lg:w-64">
                                <button
                                    type="button"
                                    wire:click="queueAnalysis({{ (int) ($item['chat_message_id'] ?? 0) }})"
                                    wire:loading.attr="disabled"
                                    @disabled(! $canQueue)
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-600 disabled:cursor-not-allowed disabled:opacity-45"
                                >
                                    {{ $busy ? 'Analiz sırada' : ((bool) ($item['already_analyzed'] ?? false) ? 'Analiz tamamlandı' : 'Bu medyayı analiz et') }}
                                </button>
                                <p class="text-[11px] leading-4 text-gray-500">Tek seferlik operatör işlemi. Otomatik tekrar, WhatsApp gönderimi veya müşteri takibi yoktur.</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center dark:border-white/15">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Analiz bekleyen fotoğraf veya belge yok.</p>
                        <p class="mt-1 text-xs text-gray-500">Yeni medya geldiğinde önce CRM’e ücretsiz olarak kaydedilir ve burada operatör seçimine sunulur.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs leading-5 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
            Güvenlik sınırı: yalnız user_id=40 / organization_id=37 / bot_id=35 / emlak-ai-35. Dosya adı ve müşteri açıklaması model talimatı olarak kabul edilmez; medya içeriği güvenilmeyen veri sayılır. Kimlik, telefon, e-posta ve IBAN gibi gereksiz kişisel bilgiler çıkarılmamalıdır. Analiz sonucu hukuki doğrulama değildir ve follow-up özelliğini açamaz.
        </section>
    </div>
</x-filament-panels::page>
