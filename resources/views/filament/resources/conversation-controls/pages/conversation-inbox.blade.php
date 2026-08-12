<x-filament-panels::page>

    <style>
        /*
        |--------------------------------------------------------------------------
        | ANA SOHBET EKRANI
        |--------------------------------------------------------------------------
        */

        .wai-chat-shell {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr) 310px;

            width: 100%;
            height: 72vh;
            min-height: 620px;
            max-height: 780px;

            overflow: hidden;

            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 10px 35px rgba(15, 23, 42, .08);
        }

        .dark .wai-chat-shell {
            background: #111827;
            border-color: rgba(255, 255, 255, .10);
        }

        /*
        |--------------------------------------------------------------------------
        | SOL TARAF
        |--------------------------------------------------------------------------
        */

        .wai-sidebar {
            display: flex;
            flex-direction: column;

            min-width: 0;
            min-height: 0;

            overflow: hidden;

            border-right: 1px solid #e5e7eb;

            background: #ffffff;
        }

        .dark .wai-sidebar {
            background: #111827;
            border-color: rgba(255, 255, 255, .10);
        }

        /*
        |--------------------------------------------------------------------------
        | ARAMA
        |--------------------------------------------------------------------------
        */

        .wai-search {
            flex: 0 0 auto;
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .wai-search {
            border-color: rgba(255, 255, 255, .10);
        }

        .wai-search-input {
            width: 100%;
            box-sizing: border-box;

            padding: 12px 14px;

            border: 1px solid #d1d5db;
            border-radius: 11px;

            background: #f9fafb;

            font-size: 14px;

            outline: none;
        }

        .wai-search-input:focus {
            border-color: #6366f1;

            box-shadow:
                0 0 0 3px rgba(99, 102, 241, .12);
        }

        .dark .wai-search-input {
            background: #1f2937;
            border-color: rgba(255, 255, 255, .10);
            color: #ffffff;
        }

        /*
        |--------------------------------------------------------------------------
        | KONUŞMA LİSTESİ
        |--------------------------------------------------------------------------
        */

        .wai-conversation-list {
            flex: 1 1 auto;
            min-height: 0;

            overflow-y: auto;
            overflow-x: hidden;
        }

        .wai-conversation {
            display: block;

            width: 100%;
            box-sizing: border-box;

            padding: 16px;

            border: 0;
            border-bottom: 1px solid #f1f5f9;

            background: transparent;

            text-align: left;

            cursor: pointer;

            transition: .15s ease;
        }

        .wai-conversation:hover {
            background: #f8fafc;
        }

        .wai-conversation.active {
            background: #eef2ff;
        }

        .dark .wai-conversation {
            border-color: rgba(255, 255, 255, .06);
            color: #ffffff;
        }

        .dark .wai-conversation:hover {
            background: rgba(255, 255, 255, .05);
        }

        .dark .wai-conversation.active {
            background: rgba(99, 102, 241, .13);
        }

        .wai-conversation-top {
            display: flex;

            gap: 10px;

            align-items: flex-start;
            justify-content: space-between;
        }

        .wai-conversation-info {
            min-width: 0;
            flex: 1;
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİ ADI / NUMARA
        |--------------------------------------------------------------------------
        */

        .wai-customer-name {
            overflow: hidden;

            font-size: 14px;
            font-weight: 700;

            color: #111827;

            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .wai-customer-name {
            color: #ffffff;
        }

        .wai-customer-number {
            margin-top: 2px;

            overflow: hidden;

            font-size: 11px;

            color: #94a3b8;

            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wai-company {
            margin-top: 4px;

            overflow: hidden;

            font-size: 12px;

            color: #64748b;

            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wai-preview {
            margin-top: 10px;

            display: -webkit-box;

            overflow: hidden;

            font-size: 13px;
            line-height: 1.45;

            color: #475569;

            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .dark .wai-preview {
            color: #cbd5e1;
        }

        .wai-time {
            margin-top: 7px;

            font-size: 11px;

            color: #94a3b8;
        }

        /*
        |--------------------------------------------------------------------------
        | SAĞ ROZETLER
        |--------------------------------------------------------------------------
        */

        .wai-side-badges {
            display: flex;

            flex: 0 0 auto;
            flex-direction: column;

            gap: 5px;

            align-items: flex-end;
        }

        /*
        |--------------------------------------------------------------------------
        | OKUNMAMIŞ MESAJ
        |--------------------------------------------------------------------------
        */

        .wai-unread {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-width: 22px;
            height: 22px;

            padding: 0 6px;

            border-radius: 999px;

            background: #22c55e;
            color: #ffffff;

            font-size: 11px;
            font-weight: 800;

            box-shadow:
                0 2px 6px rgba(34, 197, 94, .25);
        }

        /*
        |--------------------------------------------------------------------------
        | AI / İNSAN ROZETLERİ
        |--------------------------------------------------------------------------
        */

        .wai-badge {
            flex: 0 0 auto;

            padding: 5px 9px;

            border-radius: 999px;

            font-size: 10px;
            font-weight: 700;

            white-space: nowrap;
        }

        .wai-badge-ai {
            background: #dcfce7;
            color: #15803d;
        }

        .wai-badge-human {
            background: #fef3c7;
            color: #b45309;
        }

        /*
        |--------------------------------------------------------------------------
        | SAĞ TARAF
        |--------------------------------------------------------------------------
        */

        .wai-main {
            display: flex;
            flex-direction: column;

            min-width: 0;
            min-height: 0;

            height: 100%;

            overflow: hidden;

            background: #f8fafc;
        }

        .dark .wai-main {
            background: #0f172a;
        }

        /*
        |--------------------------------------------------------------------------
        | ÜST BAR
        |--------------------------------------------------------------------------
        */

        .wai-header {
            display: flex;
            flex: 0 0 auto;

            flex-wrap: wrap;

            gap: 12px;

            align-items: center;
            justify-content: space-between;

            padding: 15px 18px;

            border-bottom: 1px solid #e5e7eb;

            background: #ffffff;
        }

        .dark .wai-header {
            background: #111827;
            border-color: rgba(255, 255, 255, .10);
        }

        .wai-header-actions {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            align-items: center;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTONLAR
        |--------------------------------------------------------------------------
        */

        .wai-btn {
            border: 0;

            border-radius: 9px;

            padding: 9px 13px;

            color: #ffffff;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;
        }

        .wai-btn-human {
            background: #f59e0b;
        }

        .wai-btn-human:hover {
            background: #d97706;
        }

        .wai-btn-ai {
            background: #16a34a;
        }

        .wai-btn-ai:hover {
            background: #15803d;
        }

        /*
        |--------------------------------------------------------------------------
        | MESAJ ALANI
        |--------------------------------------------------------------------------
        */

        .wai-messages {
            flex: 1 1 0;

            min-height: 0;

            overflow-y: auto;
            overflow-x: hidden;

            padding: 22px;

            background-color: #efeae2;

            background-image:
                radial-gradient(
                    rgba(0, 0, 0, .025) 1px,
                    transparent 1px
                );

            background-size: 18px 18px;
        }

        .dark .wai-messages {
            background-color: #0b141a;
        }

        /*
        |--------------------------------------------------------------------------
        | MESAJ SATIRI
        |--------------------------------------------------------------------------
        */

        .wai-message-row {
            display: flex;

            width: 100%;

            margin-bottom: 10px;
        }

        .wai-message-row.customer {
            justify-content: flex-start;
        }

        .wai-message-row.outgoing {
            justify-content: flex-end;
        }

        /*
        |--------------------------------------------------------------------------
        | MESAJ BALONLARI
        |--------------------------------------------------------------------------
        */

        .wai-bubble {
            max-width: 72%;

            box-sizing: border-box;

            padding: 9px 11px 7px;

            border-radius: 10px;

            box-shadow:
                0 1px 1px rgba(0, 0, 0, .08);
        }

        .wai-bubble.customer {
            background: #ffffff;
            color: #111827;

            border-top-left-radius: 3px;
        }

        .wai-bubble.ai {
            background: #d9fdd3;
            color: #111827;

            border-top-right-radius: 3px;
        }

        .wai-bubble.human {
            background: #dbeafe;
            color: #111827;

            border-top-right-radius: 3px;
        }

        .wai-sender {
            margin-bottom: 4px;

            font-size: 10px;
            font-weight: 700;

            opacity: .70;
        }

        .wai-message-text {
            white-space: pre-wrap;
            word-break: break-word;

            font-size: 14px;
            line-height: 1.45;
        }

        .wai-message-time {
            margin-top: 5px;

            text-align: right;

            font-size: 9px;

            opacity: .55;
        }

        /*
        |--------------------------------------------------------------------------
        | MESAJ YAZMA ALANI
        |--------------------------------------------------------------------------
        */

        .wai-composer {
            display: block;

            flex: 0 0 auto;

            position: relative;

            z-index: 20;

            width: 100%;

            box-sizing: border-box;

            padding: 13px;

            border-top: 1px solid #d1d5db;

            background: #f0f2f5;
        }

        .dark .wai-composer {
            background: #111827;
            border-color: rgba(255, 255, 255, .10);
        }

        .wai-composer-row {
            display: flex;

            width: 100%;

            box-sizing: border-box;

            gap: 10px;

            align-items: flex-end;
        }

        .wai-textarea {
            display: block;

            flex: 1 1 auto;

            width: 100%;
            min-width: 0;

            box-sizing: border-box;

            min-height: 52px;
            max-height: 120px;

            resize: none;

            padding: 13px 14px;

            border: 1px solid #d1d5db;
            border-radius: 12px;

            background: #ffffff;

            color: #111827;

            font-family: inherit;
            font-size: 14px;

            outline: none;
        }

        .wai-textarea:focus {
            border-color: #22c55e;

            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .10);
        }

        .dark .wai-textarea {
            background: #1f2937;
            border-color: rgba(255, 255, 255, .10);
            color: #ffffff;
        }

        .wai-send {
            display: inline-flex;

            flex: 0 0 auto;

            align-items: center;
            justify-content: center;

            min-width: 105px;
            min-height: 52px;

            padding: 0 20px;

            border: 0;
            border-radius: 12px;

            background: #16a34a;

            color: #ffffff;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            transition: .15s ease;
        }

        .wai-send:hover {
            background: #15803d;
        }

        .wai-send:disabled {
            cursor: wait;
            opacity: .50;
        }

        .wai-note {
            margin-top: 7px;

            font-size: 11px;

            color: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | BOŞ EKRAN
        |--------------------------------------------------------------------------
        */

        .wai-empty {
            display: flex;

            flex: 1;

            align-items: center;
            justify-content: center;

            padding: 40px;

            text-align: center;

            color: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİ BİLGİLERİ / CRM PANELİ
        |--------------------------------------------------------------------------
        */

        .wai-customer-panel {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            overflow-y: auto;
            border-left: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .dark .wai-customer-panel {
            background: #111827;
            border-color: rgba(255, 255, 255, .10);
        }

        .wai-profile {
            padding: 24px 18px 20px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .wai-profile {
            border-color: rgba(255, 255, 255, .10);
        }

        .wai-profile-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 76px;
            height: 76px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
        }

        .wai-profile-name {
            overflow: hidden;
            font-size: 17px;
            font-weight: 800;
            color: #111827;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .wai-profile-name { color: #ffffff; }

        .wai-profile-number {
            margin-top: 5px;
            font-size: 12px;
            color: #64748b;
        }

        .wai-profile-company {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .dark .wai-profile-company { color: #cbd5e1; }

        .wai-crm-section {
            padding: 16px 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .wai-crm-section { border-color: rgba(255, 255, 255, .08); }

        .wai-crm-title {
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #64748b;
        }

        .wai-crm-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
        }

        .wai-crm-label {
            flex: 0 0 auto;
            font-size: 12px;
            color: #64748b;
        }

        .wai-crm-value {
            min-width: 0;
            overflow: hidden;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .wai-crm-value { color: #ffffff; }

        .wai-crm-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .wai-crm-status.ai {
            background: #dcfce7;
            color: #15803d;
        }

        .wai-crm-status.human {
            background: #fef3c7;
            color: #b45309;
        }

        .wai-crm-tag {
            display: inline-flex;
            margin: 3px 3px 0 0;
            padding: 5px 8px;
            border-radius: 7px;
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
        }

        .dark .wai-crm-tag {
            background: #1f2937;
            color: #cbd5e1;
        }

        .wai-crm-empty {
            padding: 10px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            background: #f8fafc;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.45;
        }

        .dark .wai-crm-empty {
            background: #0f172a;
            border-color: rgba(255, 255, 255, .12);
        }

        .wai-crm-action {
            width: 100%;
            margin-top: 10px;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #ffffff;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .dark .wai-crm-action {
            background: #1f2937;
            border-color: rgba(255, 255, 255, .10);
            color: #94a3b8;
        }

        /*
        |--------------------------------------------------------------------------
        | MOBİL
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .wai-chat-shell {
                display: grid;

                grid-template-columns: 1fr;

                height: auto;
                min-height: 0;
                max-height: none;
            }

            .wai-sidebar {
                height: 320px;

                border-right: 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .wai-main {
                height: 650px;
                min-height: 650px;
            }

            .wai-customer-panel {
                border-left: 0;
                border-top: 1px solid #e5e7eb;
            }

            .wai-bubble {
                max-width: 88%;
            }

            .wai-header {
                padding: 12px;
            }

            .wai-messages {
                padding: 14px;
            }

            .wai-composer {
                padding: 10px;
            }

            .wai-send {
                min-width: 85px;

                padding: 0 14px;
            }
        }
    </style>


    <div
        class="wai-chat-shell"
        wire:poll.3s
    >

        {{-- ================================================================
             SOL TARAF
        ================================================================= --}}

        <aside class="wai-sidebar">

            {{-- ARAMA --}}

            <div class="wai-search">

                <input
                    class="wai-search-input"
                    type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="İsim, telefon veya firma ara..."
                >

            </div>


            {{-- KONUŞMALAR --}}

            <div class="wai-conversation-list">

                @forelse ($this->conversations as $conversation)

                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->id }})"
                        class="wai-conversation {{ $selectedConversationId === $conversation->id ? 'active' : '' }}"
                    >

                        <div class="wai-conversation-top">

                            <div class="wai-conversation-info">

                                <div class="wai-customer-name">

                                    {{
                                        $conversation->customer_name
                                            ?: $conversation->whatsapp_number
                                    }}

                                </div>

                                @if ($conversation->customer_name)

                                    <div class="wai-customer-number">
                                        {{ $conversation->whatsapp_number }}
                                    </div>

                                @endif

                                <div class="wai-company">

                                    {{
                                        $conversation->aiBot?->company_name
                                        ?? $conversation->aiBot?->name
                                        ?? 'WhatsApp'
                                    }}

                                </div>

                            </div>


                            <div class="wai-side-badges">

                                @if ((int) $conversation->unread_count > 0)

                                    <span class="wai-unread">

                                        {{
                                            (int) $conversation->unread_count > 99
                                                ? '99+'
                                                : (int) $conversation->unread_count
                                        }}

                                    </span>

                                @endif


                                @if ($conversation->human_takeover)

                                    <span class="wai-badge wai-badge-human">
                                        👤 İnsan
                                    </span>

                                @else

                                    <span class="wai-badge wai-badge-ai">
                                        🤖 AI
                                    </span>

                                @endif

                            </div>

                        </div>


                        <div class="wai-preview">
                            {{ $conversation->last_message_preview }}
                        </div>


                        @if ($conversation->last_message_at)

                            <div class="wai-time">
                                {{ $conversation->last_message_at->diffForHumans() }}
                            </div>

                        @endif

                    </button>

                @empty

                    <div class="wai-empty">
                        Henüz konuşma bulunmuyor.
                    </div>

                @endforelse

            </div>

        </aside>


        {{-- ================================================================
             SAĞ TARAF
        ================================================================= --}}

        <main class="wai-main">

            @if ($this->selectedConversation)


                {{-- ========================================================
                     ÜST BAR
                ========================================================= --}}

                <header class="wai-header">

                    <div>

                        <div class="wai-customer-name">

                            {{
                                $this->selectedConversation->customer_name
                                    ?: $this->selectedConversation->whatsapp_number
                            }}

                        </div>

                        @if ($this->selectedConversation->customer_name)

                            <div class="wai-customer-number">
                                {{ $this->selectedConversation->whatsapp_number }}
                            </div>

                        @endif

                        <div class="wai-company">

                            {{
                                $this->selectedConversation->aiBot?->company_name
                                ?? $this->selectedConversation->aiBot?->name
                                ?? 'WhatsApp'
                            }}

                        </div>

                    </div>


                    <div class="wai-header-actions">

                        @if ($this->selectedConversation->human_takeover)

                            <span class="wai-badge wai-badge-human">
                                👤 İnsan Yönetiyor
                            </span>

                            <button
                                class="wai-btn wai-btn-ai"
                                type="button"
                                wire:click="releaseToAi"
                            >
                                🤖 AI’ye Geri Ver
                            </button>

                        @else

                            <span class="wai-badge wai-badge-ai">
                                🤖 AI Aktif
                            </span>

                            <button
                                class="wai-btn wai-btn-human"
                                type="button"
                                wire:click="takeOver"
                            >
                                👤 İnsan Devral
                            </button>

                        @endif

                    </div>

                </header>


                {{-- ========================================================
                     MESAJLAR
                ========================================================= --}}

                <section
                    class="wai-messages"
                    wire:key="conversation-messages-{{ $selectedConversationId }}-{{ $this->messages->count() }}"
                    x-data
                    x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                >

                    @forelse ($this->messages as $message)

                        @php

                            $senderType =
                                $message->sender_type;

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
                            class="wai-message-row {{ $isCustomer ? 'customer' : 'outgoing' }}"
                        >

                            <div
                                class="wai-bubble
                                {{
                                    $isCustomer
                                        ? 'customer'
                                        : (
                                            $isHuman
                                                ? 'human'
                                                : 'ai'
                                        )
                                }}"
                            >

                                <div class="wai-sender">

                                    @if ($isCustomer)

                                        Müşteri

                                    @elseif ($isHuman)

                                        👤 {{
                                            $message->sentByUser?->name
                                            ?? 'Personel'
                                        }}

                                    @else

                                        🤖 Yapay Zekâ

                                    @endif

                                </div>


                                <div class="wai-message-text">
                                    {{ $message->message }}
                                </div>


                                <div class="wai-message-time">

                                    {{
                                        $message->created_at?->format('H:i')
                                    }}

                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="wai-empty">
                            Bu konuşmada henüz mesaj bulunmuyor.
                        </div>

                    @endforelse

                </section>


                {{-- ========================================================
                     MESAJ YAZMA / GÖNDERME
                ========================================================= --}}

                <form
                    class="wai-composer"
                    wire:submit="sendMessage"
                >

                    <div class="wai-composer-row">

                        <textarea
                            class="wai-textarea"
                            wire:model="messageText"
                            placeholder="Mesaj yaz..."
                            rows="2"
                        ></textarea>


                        <button
                            class="wai-send"
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                        >

                            <span
                                wire:loading.remove
                                wire:target="sendMessage"
                            >
                                Gönder
                            </span>

                            <span
                                wire:loading
                                wire:target="sendMessage"
                            >
                                Gönderiliyor...
                            </span>

                        </button>

                    </div>


                    @if (! $this->selectedConversation->human_takeover)

                        <div class="wai-note">

                            Mesaj gönderdiğiniz anda bu konuşma otomatik olarak
                            <strong>İnsan Yönetiyor</strong>
                            moduna geçer.

                        </div>

                    @endif

                </form>


            @else


                {{-- ========================================================
                     KONUŞMA SEÇİLMEDİ
                ========================================================= --}}

                <div class="wai-empty">

                    <div>

                        <div style="font-size:42px;">
                            💬
                        </div>

                        <div
                            style="
                                margin-top:10px;
                                font-weight:700;
                            "
                        >
                            Bir konuşma seçin
                        </div>

                        <div
                            style="
                                margin-top:5px;
                                font-size:13px;
                            "
                        >
                            Soldaki listeden bir WhatsApp konuşması seçebilirsiniz.
                        </div>

                    </div>

                </div>


            @endif

        </main>

        @if ($this->selectedConversation)

            @php
                $selectedCustomerName =
                    $this->selectedConversation->customer_name
                    ?: $this->selectedConversation->whatsapp_number;

                $selectedCompany =
                    $this->selectedConversation->aiBot?->company_name
                    ?? $this->selectedConversation->aiBot?->name
                    ?? 'WhatsApp';

                $customerInitial =
                    mb_strtoupper(
                        mb_substr(
                            trim((string) $selectedCustomerName),
                            0,
                            1
                        )
                    );
            @endphp

            <aside class="wai-customer-panel">

                <div class="wai-profile">
                    <div class="wai-profile-avatar">
                        {{ $customerInitial ?: '👤' }}
                    </div>

                    <div class="wai-profile-name">
                        {{ $selectedCustomerName }}
                    </div>

                    <div class="wai-profile-number">
                        {{ $this->selectedConversation->whatsapp_number }}
                    </div>

                    <div class="wai-profile-company">
                        🏢 {{ $selectedCompany }}
                    </div>
                </div>

                <div class="wai-crm-section">
                    <div class="wai-crm-title">Müşteri Bilgileri</div>

                    <div class="wai-crm-row">
                        <span class="wai-crm-label">Telefon</span>
                        <span class="wai-crm-value">
                            {{ $this->selectedConversation->whatsapp_number }}
                        </span>
                    </div>

                    <div class="wai-crm-row">
                        <span class="wai-crm-label">Firma</span>
                        <span class="wai-crm-value">{{ $selectedCompany }}</span>
                    </div>

                    <div class="wai-crm-row">
                        <span class="wai-crm-label">Son aktivite</span>
                        <span class="wai-crm-value">
                            {{
                                $this->selectedConversation->last_message_at
                                    ? $this->selectedConversation->last_message_at->diffForHumans()
                                    : 'Bilinmiyor'
                            }}
                        </span>
                    </div>
                </div>

                <div class="wai-crm-section">
                    <div class="wai-crm-title">Konuşma Durumu</div>

                    <div class="wai-crm-row">
                        <span class="wai-crm-label">Yönetim</span>

                        @if ($this->selectedConversation->human_takeover)
                            <span class="wai-crm-status human">👤 İnsan</span>
                        @else
                            <span class="wai-crm-status ai">🤖 AI Aktif</span>
                        @endif
                    </div>

                    <div class="wai-crm-row">
                        <span class="wai-crm-label">Okunmamış</span>
                        <span class="wai-crm-value">
                            {{ (int) $this->selectedConversation->unread_count }}
                        </span>
                    </div>
                </div>

                <div class="wai-crm-section">
                    <div class="wai-crm-title">Etiketler</div>

                    <span class="wai-crm-tag">WhatsApp</span>

                    @if ($this->selectedConversation->human_takeover)
                        <span class="wai-crm-tag">İnsan Yönetiyor</span>
                    @else
                        <span class="wai-crm-tag">AI Aktif</span>
                    @endif
                </div>

                <div class="wai-crm-section">
                    <div class="wai-crm-title">Notlar</div>

                    <div class="wai-crm-empty">
                        Müşteri notları bir sonraki adımda kalıcı olarak eklenecek.
                    </div>

                    <button
                        type="button"
                        class="wai-crm-action"
                        disabled
                    >
                        📝 Not Ekle — Yakında
                    </button>
                </div>

                <div class="wai-crm-section">
                    <div class="wai-crm-title">Siparişler</div>

                    <div class="wai-crm-empty">
                        Müşterinin sipariş geçmişini burada göstereceğiz.
                    </div>
                </div>

            </aside>

        @endif

    </div>

</x-filament-panels::page>