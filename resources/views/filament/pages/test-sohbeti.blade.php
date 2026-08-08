<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-6">

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-4">

                <div>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                        🤖 Yapay Zekânızı Test Edin
                    </h2>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        WhatsApp bağlantısını kurmadan önce cevapları burada deneyebilirsiniz.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="sohbetiTemizle"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Sohbeti Temizle
                </button>

            </div>
        </div>

        <div class="min-h-[420px] space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            @foreach ($mesajlar as $mesajKaydi)

                @if ($mesajKaydi['rol'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-2xl rounded-2xl rounded-br-sm bg-primary-600 px-4 py-3 text-sm text-white">
                            {{ $mesajKaydi['metin'] }}
                        </div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-2xl rounded-2xl rounded-bl-sm bg-gray-100 px-4 py-3 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                            {{ $mesajKaydi['metin'] }}
                        </div>
                    </div>
                @endif

            @endforeach

            <div wire:loading wire:target="mesajGonder" class="flex justify-start">
                <div class="rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Yapay zekâ düşünüyor...
                </div>
            </div>

        </div>

        <form
            wire:submit="mesajGonder"
            class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <div class="flex gap-3">

                <input
                    type="text"
                    wire:model="mesaj"
                    placeholder="Mesajınızı yazın..."
                    autocomplete="off"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                >

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="mesajGonder"
                    class="rounded-lg bg-primary-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Gönder
                </button>

            </div>
        </form>

    </div>
</x-filament-panels::page>