[x-filament-panels::page](x-filament-panels::page)

```
<div class="space-y-6">

    {{-- ÜST KARŞILAMA KARTI --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">

        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">

            <div>
                <div class="mb-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                    AsilkanSoft AI Kurulum Merkezi
                </div>

                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    Hoş geldiniz, {{ $user->name }} 👋
                </h2>

                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Yapay zekâ sisteminizi canlı kullanıma hazırlamak için aşağıdaki adımları tamamlayın.
                    Kurulum durumunuzu bu ekrandan takip edebilirsiniz.
                </p>
            </div>

            <div class="min-w-[220px] rounded-2xl bg-gray-50 p-5 dark:bg-white/5">

                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Kurulum İlerlemesi
                        </div>

                        <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
                            %{{ $progress }}
                        </div>
                    </div>

                    <div class="text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ $completedCount }}/{{ $totalSteps }} tamamlandı
                    </div>
                </div>

                <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all duration-500"
                        style="width: {{ $progress }}%"
                    ></div>
                </div>

            </div>

        </div>

    </div>


    {{-- TAMAMLANDI MESAJI --}}
    @if ($progress === 100)

        <div class="rounded-2xl border border-success-200 bg-success-50 p-5 dark:border-success-500/20 dark:bg-success-500/10">

            <div class="flex items-start gap-4">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-success-100 text-xl dark:bg-success-500/20">
                    ✅
                </div>

                <div>
                    <h3 class="font-semibold text-success-800 dark:text-success-300">
                        Kurulumunuz tamamlandı
                    </h3>

                    <p class="mt-1 text-sm leading-6 text-success-700 dark:text-success-400">
                        Yapay zekâ sisteminiz kullanıma hazır. WhatsApp üzerinden gelen müşterilere yanıt verebilir
                        ve panel üzerinden siparişlerinizi takip edebilirsiniz.
                    </p>
                </div>

            </div>

        </div>

    @endif


    {{-- KURULUM ADIMLARI --}}
    <div>

        <div class="mb-4">

            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                Kurulum Adımları
            </h3>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Her adımı tamamladığınızda ilerleme oranınız otomatik güncellenir.
            </p>

        </div>


        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">

            @foreach ($steps as $index => $step)

                <div
                    class="
                        relative overflow-hidden rounded-2xl border p-5 transition
                        {{ $step['completed']
                            ? 'border-success-200 bg-success-50/50 dark:border-success-500/20 dark:bg-success-500/5'
                            : 'border-gray-200 bg-white hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/40'
                        }}
                    "
                >

                    <div class="flex gap-4">

                        {{-- NUMARA / İKON --}}
                        <div class="shrink-0">

                            <div
                                class="
                                    flex h-12 w-12 items-center justify-center rounded-xl text-xl
                                    {{ $step['completed']
                                        ? 'bg-success-100 dark:bg-success-500/20'
                                        : 'bg-gray-100 dark:bg-white/5'
                                    }}
                                "
                            >
                                {{ $step['icon'] }}
                            </div>

                        </div>


                        {{-- İÇERİK --}}
                        <div class="min-w-0 flex-1">

                            <div class="flex flex-wrap items-center gap-2">

                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                    Adım {{ $index + 1 }}
                                </span>

                                @if ($step['completed'])

                                    <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/20 dark:text-success-300">
                                        ✓ Tamamlandı
                                    </span>

                                @else

                                    <span class="inline-flex items-center rounded-full bg-warning-100 px-2.5 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">
                                        Bekliyor
                                    </span>

                                @endif

                            </div>


                            <h4 class="mt-2 text-base font-semibold text-gray-950 dark:text-white">
                                {{ $step['title'] }}
                            </h4>


                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                {{ $step['description'] }}
                            </p>


                            <div class="mt-4">

                                <a
                                    href="{{ $step['url'] }}"
                                    class="
                                        inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition
                                        {{ $step['completed']
                                            ? 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10'
                                            : 'bg-primary-600 text-white hover:bg-primary-500'
                                        }}
                                    "
                                >
                                    {{ $step['button'] }}

                                    <span aria-hidden="true">
                                        →
                                    </span>
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    </div>


    {{-- ALT DESTEK KARTI --}}
    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-white/10 dark:bg-white/5">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>

                <h3 class="font-semibold text-gray-950 dark:text-white">
                    Kurulum sırasında yardıma mı ihtiyacınız var?
                </h3>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Kurulum adımlarında sorun yaşarsanız AsilkanSoft destek ekibiyle iletişime geçebilirsiniz.
                </p>

            </div>

            <a
                href="#"
                class="inline-flex shrink-0 items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
            >
                Destek Al
            </a>

        </div>

    </div>

</div>
```

</x-filament-panels::page>
