<x-filament-panels::page>

    <div class="space-y-6">

        {{-- ÜST KARŞILAMA --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">

            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">

                <div>
                    <p class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                        AsilkanSoft AI Kurulum Merkezi
                    </p>

                    <h2 class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        Hoş geldiniz, {{ $user->name }} 👋
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Yapay zekâ sisteminizi canlı kullanıma hazırlamak için aşağıdaki adımları tamamlayın.
                    </p>
                </div>

                <div class="min-w-[220px] rounded-2xl bg-gray-50 p-5 dark:bg-white/5">

                    <div class="flex items-end justify-between gap-4">

                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Kurulum İlerlemesi
                            </p>

                            <p class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
                                %{{ $progress }}
                            </p>
                        </div>

                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                            {{ $completedCount }}/{{ $totalSteps }}
                        </p>

                    </div>

                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div
                            class="h-full rounded-full bg-primary-600 transition-all duration-500"
                            style="width: {{ $progress }}%;"
                        ></div>
                    </div>

                </div>

            </div>

        </div>


        {{-- KURULUM TAMAMLANDI --}}
        @if ($progress === 100)

            <div class="rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-500/20 dark:bg-green-500/10">

                <div class="flex items-start gap-4">

                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-green-100 text-xl dark:bg-green-500/20">
                        ✅
                    </div>

                    <div>
                        <h3 class="font-semibold text-green-800 dark:text-green-300">
                            Kurulum tamamlandı
                        </h3>

                        <p class="mt-1 text-sm leading-6 text-green-700 dark:text-green-400">
                            Yapay zekâ sisteminiz kullanıma hazır.
                        </p>
                    </div>

                </div>

            </div>

        @endif


        {{-- BAŞLIK --}}
        <div>

            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                Kurulum Adımları
            </h3>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Her adımı tamamladığınızda ilerleme oranınız otomatik güncellenir.
            </p>

        </div>


        {{-- KARTLAR --}}
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">

            @foreach ($steps as $index => $step)

                <div
                    class="rounded-2xl border p-5 transition
                    {{ $step['completed']
                        ? 'border-green-200 bg-green-50/50 dark:border-green-500/20 dark:bg-green-500/5'
                        : 'border-gray-200 bg-white hover:shadow-md dark:border-white/10 dark:bg-gray-900'
                    }}"
                >

                    <div class="flex gap-4">

                        <div class="shrink-0">

                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl text-xl
                                {{ $step['completed']
                                    ? 'bg-green-100 dark:bg-green-500/20'
                                    : 'bg-gray-100 dark:bg-white/5'
                                }}"
                            >
                                {{ $step['icon'] }}
                            </div>

                        </div>

                        <div class="min-w-0 flex-1">

                            <div class="flex flex-wrap items-center gap-2">

                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                    Adım {{ $index + 1 }}
                                </span>

                                @if ($step['completed'])

                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-500/20 dark:text-green-300">
                                        ✓ Tamamlandı
                                    </span>

                                @else

                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
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
                                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition
                                    {{ $step['completed']
                                        ? 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10'
                                        : 'bg-primary-600 text-white hover:bg-primary-500'
                                    }}"
                                >
                                    {{ $step['button'] }}
                                    <span>→</span>
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            @endforeach

        </div>


        {{-- DESTEK --}}
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-white/10 dark:bg-white/5">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <h3 class="font-semibold text-gray-950 dark:text-white">
                        Kurulum sırasında yardıma mı ihtiyacınız var?
                    </h3>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        AsilkanSoft destek ekibi kurulum sürecinde size yardımcı olabilir.
                    </p>

                </div>

                <a
                    href="#"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                >
                    Destek Al
                </a>

            </div>

        </div>

    </div>

</x-filament-panels::page>