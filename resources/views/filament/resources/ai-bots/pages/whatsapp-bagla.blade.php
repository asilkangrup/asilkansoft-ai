<x-filament-panels::page>
    <div class="space-y-6">

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-4">

                <div>
                    <h2 class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $record->name }}
                    </h2>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        WhatsApp bağlantınızı bu ekrandan yönetebilirsiniz.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Durumu Yenile
                </button>

            </div>
        </div>

        <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            @if ($record->whatsapp_status === 'connected')

                <div class="text-6xl">
                    ✅
                </div>

                <div class="mt-4 text-2xl font-bold text-green-600">
                    WhatsApp Bağlandı
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Numaranız yapay zekâ sistemine başarıyla bağlandı.
                </p>

            @elseif (! empty($record->whatsapp_qr))

                <h3 class="mb-6 text-xl font-bold text-gray-950 dark:text-white">
                    Telefonunuzla QR kodu okutun
                </h3>

                <div class="mx-auto w-fit rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <img
                        src="{{ $record->whatsapp_qr }}"
                        alt="WhatsApp QR Kod"
                        class="block h-80 w-80 object-contain"
                    >
                </div>

                <p class="mt-5 text-sm font-medium text-gray-700 dark:text-gray-300">
                    WhatsApp → Ayarlar → Bağlı Cihazlar → Cihaz Bağla
                </p>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    QR süresi dolarsa Durumu Yenile butonuna basın.
                </p>

            @else

                <div class="text-5xl">
                    📱
                </div>

                <div class="mt-4 text-xl font-bold text-gray-950 dark:text-white">
                    WhatsApp bağlantısı hazırlanıyor
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Durumu Yenile butonuna basarak güncel bağlantı bilgisini alın.
                </p>

            @endif

        </div>

    </div>
</x-filament-panels::page>