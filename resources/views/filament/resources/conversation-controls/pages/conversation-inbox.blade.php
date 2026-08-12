<x-filament-panels::page>

    <div
        wire:poll.3s
        class="grid min-h-[72vh] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900 lg:grid-cols-[340px_minmax(0,1fr)]"
    >

        {{-- SOL: KONUŞMALAR --}}
        <div class="border-b border-gray-200 dark:border-white/10 lg:border-b-0 lg:border-r">

            {{-- ARAMA --}}
            <div class="border-b border-gray-200 p-4 dark:border-white/10">
                <input
                    type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Konuşma ara..."
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800"
                />
            </div>

            {{-- LİSTE --}}
            <div class="max-h-[68vh] overflow-y-auto">

                @forelse ($this->conversations as $conversation)

                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->id }})"
                        class="w-full border-b border-gray-100 px-4 py-4 text-left transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5
                            {{ $selectedConversationId === $conversation->id
                                ? 'bg-primary-50 dark:bg-primary-500/10'
                                : ''
                            }}"
                    >

                        <div class="flex items-start justify-between gap-3">

                            <div class="min-w-0">

                                <div class="truncate font-semibold text-gray-950 dark:text-white">
                                    {{ $conversation->whatsapp_number }}
                                </div>

                                <div class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $conversation->aiBot?->company_name
                                        ?? $conversation->aiBot?->name
                                        ?? 'Yapay Zekâ'
                                    }}
                                </div>

                            </div>

                            @if ($conversation->human_takeover)

                                <span class="shrink-0 rounded-full bg-warning-100 px-2 py-1 text-[10px] font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                    👤 İnsan
                                </span>

                            @else

                                <span class="shrink-0 rounded-full bg-success-100 px-2 py-1 text-[10px] font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                    🤖 AI
                                </span>

                            @endif

                        </div>

                        <div class="mt-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ $conversation->last_message_preview }}
                        </div>

                        @if ($conversation->last_message_at)

                            <div class="mt-2 text-[11px] text-gray-400">
                                {{ $conversation->last_message_at->diffForHumans() }}
                            </div>

                        @endif

                    </button>

                @empty

                    <div class="p-8 text-center text-sm text-gray-500">
                        Henüz konuşma bulunmuyor.
                    </div>

                @endforelse

            </div>

        </div>

        {{-- SAĞ: SOHBET --}}
        <div class="flex min-h-[72vh] min-w-0 flex-col">

            @if ($this->selectedConversation)

                {{-- ÜST BAR --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">

                    <div>

                        <div class="font-semibold text-gray-950 dark:text-white">
                            {{ $this->selectedConversation->whatsapp_number }}
                        </div>

                        <div class="mt-1 text-xs text-gray-500">
                            {{ $this->selectedConversation->aiBot?->company_name
                                ?? $this->selectedConversation->aiBot?->name
                                ?? 'WhatsApp'
                            }}
                        </div>

                    </div>

                    <div class="flex flex-wrap items-center gap-2">

                        @if ($this->selectedConversation->human_takeover)

                            <span class="rounded-full bg-warning-100 px-3 py-1.5 text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                👤 İnsan Yönetiyor
                            </span>

                            <button
                                type="button"
                                wire:click="releaseToAi"
                                class="rounded-lg bg-success-600 px-3 py-2 text-sm font-semibold text-white hover:bg-success-500"
                            >
                                🤖 AI’ye Geri Ver
                            </button>

                        @else

                            <span class="rounded-full bg-success-100 px-3 py-1.5 text-xs font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                🤖 AI Aktif
                            </span>

                            <button
                                type="button"
                                wire:click="takeOver"
                                class="rounded-lg bg-warning-500 px-3 py-2 text-sm font-semibold text-white hover:bg-warning-400"
                            >
                                👤 İnsan Devral
                            </button>

                        @endif

                    </div>

                </div>

                {{-- MESAJLAR --}}
                <div
                    wire:key="chat-{{ $selectedConversationId }}-{{ $this->messages->count() }}"
                    x-data
                    x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                    class="flex-1 space-y-4 overflow-y-auto bg-gray-50 p-5 dark:bg-gray-950/40"
                >

                    @forelse ($this->messages as $message)

                        @php
                            $senderType = $message->sender_type;

                            if (! $senderType) {
                                $senderType =
                                    $message->role === 'assistant'
                                        ? 'ai'
                                        : 'customer';
                            }

                            $isCustomer =
                                $senderType === 'customer';

                            $isHuman =
                                $senderType === 'human';
                        @endphp

                        <div
                            class="flex {{ $isCustomer ? 'justify-start' : 'justify-end' }}"
                        >

                            <div
                                class="max-w-[85%] rounded-2xl px-4 py-3 shadow-sm lg:max-w-[70%]
                                    @if ($isCustomer)
                                        bg-white text-gray-900 dark:bg-gray-800 dark:text-white
                                    @elseif ($isHuman)
                                        bg-primary-600 text-white
                                    @else
                                        bg-success-600 text-white
                                    @endif
                                "
                            >

                                <div class="mb-1 text-[11px] font-semibold opacity-75">

                                    @if ($isCustomer)

                                        Müşteri

                                    @elseif ($isHuman)

                                        👤 {{ $message->sentByUser?->name ?? 'Personel' }}

                                    @else

                                        🤖 Yapay Zekâ

                                    @endif

                                </div>

                                <div class="whitespace-pre-wrap break-words text-sm">
                                    {{ $message->message }}
                                </div>

                                <div class="mt-2 text-right text-[10px] opacity-60">
                                    {{ $message->created_at?->format('H:i') }}
                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="flex h-full items-center justify-center text-sm text-gray-500">
                            Bu konuşmada henüz mesaj yok.
                        </div>

                    @endforelse

                </div>

                {{-- MESAJ YAZ --}}
                <form
                    wire:submit="sendMessage"
                    class="border-t border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900"
                >

                    <div class="flex items-end gap-3">

                        <textarea
                            wire:model="messageText"
                            rows="2"
                            placeholder="Mesaj yaz..."
                            class="min-h-[52px] flex-1 resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800"
                        ></textarea>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="rounded-xl bg-primary-600 px-5 py-3 font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                        >
                            Gönder
                        </button>

                    </div>

                    @if (! $this->selectedConversation->human_takeover)

                        <div class="mt-2 text-xs text-gray-500">
                            Mesaj gönderdiğiniz anda bu konuşma otomatik olarak
                            <strong>İnsan Yönetiyor</strong> moduna geçer.
                        </div>

                    @endif

                </form>

            @else

                <div class="flex flex-1 items-center justify-center p-10 text-center">

                    <div>

                        <div class="text-4xl">
                            💬
                        </div>

                        <div class="mt-4 font-semibold text-gray-950 dark:text-white">
                            Bir konuşma seçin
                        </div>

                        <div class="mt-2 text-sm text-gray-500">
                            Soldaki listeden bir WhatsApp konuşması seçebilirsiniz.
                        </div>

                    </div>

                </div>

            @endif

        </div>

    </div>

</x-filament-panels::page>