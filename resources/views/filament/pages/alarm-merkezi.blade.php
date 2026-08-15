<x-filament-panels::page>

<style>
.wai-alarm-center {
    --green:#24e17f;
    --ink:#101712;
    --muted:#78837c;
    --line:#e5ebe7;
}

.alarm-hero {
    padding:26px;
    margin-bottom:16px;
    border:1px solid var(--line);
    border-radius:22px;
    background:
        radial-gradient(circle at 90% 10%, rgba(255,90,90,.08), transparent 32%),
        #fff;
}

.alarm-hero h1 {
    margin:0;
    color:var(--ink);
    font-size:28px;
    font-weight:900;
    letter-spacing:-.8px;
}

.alarm-hero p {
    margin:8px 0 0;
    color:var(--muted);
    font-size:12px;
    line-height:1.65;
}

.alarm-kpis {
    margin-bottom:14px;
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}

.alarm-kpi {
    padding:17px;
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
}

.alarm-kpi strong {
    display:block;
    color:var(--ink);
    font-size:26px;
    font-weight:900;
}

.alarm-kpi span {
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:10px;
    font-weight:800;
}

.alarm-toolbar {
    margin-bottom:14px;
    padding:11px;
    display:grid;
    grid-template-columns:180px 180px minmax(220px,1fr) auto;
    gap:8px;
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
}

.alarm-select,
.alarm-reset {
    height:42px;
    padding:0 11px;
    border:1px solid #dfe6e2;
    border-radius:11px;
    background:#fafcfb;
    font-size:11px;
    font-weight:750;
}

.alarm-reset {
    background:#fff;
    cursor:pointer;
}

.alarm-list {
    display:grid;
    gap:10px;
}

.alarm-card {
    padding:17px;
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    border:1px solid var(--line);
    border-radius:17px;
    background:#fff;
}

.alarm-card.critical {
    border-color:#f4c8c8;
    background:linear-gradient(145deg,#fff,#fff9f9);
}

.alarm-card.warning {
    border-color:#f2dfb4;
    background:linear-gradient(145deg,#fff,#fffdf7);
}

.alarm-top {
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:7px;
}

.alarm-badge {
    min-height:25px;
    padding:0 8px;
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    font-size:8px;
    font-weight:900;
}

.alarm-badge.critical {
    color:#9d2f2f;
    background:#ffe9e9;
}

.alarm-badge.warning {
    color:#8b6410;
    background:#fff4d7;
}

.alarm-type {
    color:#66736b;
    font-size:9px;
    font-weight:850;
}

.alarm-title {
    margin-top:10px;
    color:var(--ink);
    font-size:14px;
    font-weight:900;
}

.alarm-message {
    margin-top:6px;
    color:#657168;
    font-size:10px;
    line-height:1.6;
}

.alarm-meta {
    margin-top:10px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    color:#8b968f;
    font-size:9px;
    font-weight:750;
}

.alarm-actions {
    min-width:125px;
    display:flex;
    flex-direction:column;
    gap:7px;
}

.alarm-button {
    min-height:36px;
    padding:0 10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid #dce5df;
    border-radius:10px;
    color:#334238;
    background:#fff;
    font-size:9px;
    font-weight:900;
    text-decoration:none;
    cursor:pointer;
}

.alarm-button.primary {
    border-color:#bce9cf;
    color:#075a32;
    background:#ecfff4;
}

.alarm-empty {
    padding:44px;
    text-align:center;
    border:1px dashed #dce5df;
    border-radius:17px;
    color:#89948d;
    background:#fff;
    font-size:11px;
}

@media(max-width:800px) {
    .alarm-kpis,
    .alarm-toolbar {
        grid-template-columns:1fr;
    }

    .alarm-card {
        grid-template-columns:1fr;
    }

    .alarm-actions {
        min-width:0;
        flex-direction:row;
    }

    .alarm-button {
        flex:1;
    }

    .alarm-select {
        font-size:16px;
    }
}
</style>

<div class="wai-alarm-center">

    <section class="alarm-hero">
        <h1>WAI Alarm Merkezi</h1>

        <p>
            Kritik satış fırsatlarını, geciken sıcak leadleri ve kayıp riski taşıyan
            müşterileri tek ekrandan yönetin.
        </p>
    </section>


    <section class="alarm-kpis">

        <div class="alarm-kpi">
            <strong>{{ $this->activeCount }}</strong>
            <span>Aktif Alarm</span>
        </div>

        <div class="alarm-kpi">
            <strong>{{ $this->criticalCount }}</strong>
            <span>Kritik Alarm</span>
        </div>

        <div class="alarm-kpi">
            <strong>{{ $this->resolvedTodayCount }}</strong>
            <span>Bugün Çözülen</span>
        </div>

    </section>


    <section class="alarm-toolbar">

        <select
            class="alarm-select"
            wire:model.live="statusFilter"
        >
            <option value="active">Aktif Alarmlar</option>
            <option value="resolved">Çözülenler</option>
            <option value="all">Tümü</option>
        </select>

        <select
            class="alarm-select"
            wire:model.live="severityFilter"
        >
            <option value="all">Tüm Seviyeler</option>
            <option value="critical">Kritik</option>
            <option value="warning">Uyarı</option>
        </select>

        <select
            class="alarm-select"
            wire:model.live="typeFilter"
        >
            <option value="all">Tüm Alarm Türleri</option>
            <option value="critical_lead">Kritik Lead</option>
            <option value="hot_follow_up_overdue">Geciken Sıcak Lead</option>
            <option value="risk_lead">Riskli Lead</option>
            <option value="proposal_silent">Sessiz Teklif</option>
        </select>

        <button
            type="button"
            class="alarm-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    <section class="alarm-list">

        @forelse ($this->alarms as $alarm)

            @php
                $conversation =
                    $alarm->conversation;
            @endphp

            <article
                class="
                    alarm-card
                    {{ $alarm->severity }}
                "
            >

                <div>

                    <div class="alarm-top">

                        <span
                            class="
                                alarm-badge
                                {{ $alarm->severity }}
                            "
                        >
                            {{ $alarm->severityLabel() }}
                        </span>

                        <span class="alarm-type">
                            {{ $alarm->typeLabel() }}
                        </span>

                    </div>

                    <div class="alarm-title">
                        {{ $alarm->title }}
                    </div>

                    <div class="alarm-message">
                        {{ $alarm->message }}
                    </div>

                    <div class="alarm-meta">

                        <span>
                            Müşteri:
                            {{
                                $conversation?->customer_name
                                ?: $conversation?->whatsapp_number
                                ?: 'Silinmiş müşteri'
                            }}
                        </span>

                        @if ($conversation?->lead_score !== null)
                            <span>
                                Lead:
                                {{ $conversation->lead_score }}/100
                            </span>
                        @endif

                        @if ($conversation?->aiBot)
                            <span>
                                Bot:
                                {{ $conversation->aiBot->name }}
                            </span>
                        @endif

                        @if ($conversation?->assignedUser)
                            <span>
                                Sorumlu:
                                {{ $conversation->assignedUser->name }}
                            </span>
                        @endif

                        <span>
                            {{ $alarm->created_at->format('d.m.Y H:i') }}
                        </span>

                    </div>

                </div>


                <div class="alarm-actions">

                    @if ($conversation)

                        <a
                            class="alarm-button primary"
                            href="{{
                                url(
                                    '/admin/musteriler?customer='
                                    .$conversation->id
                                )
                            }}"
                        >
                            Müşteriyi Aç
                        </a>

                    @endif

                    @if (! $alarm->is_resolved)

                        <button
                            type="button"
                            class="alarm-button"
                            wire:click="resolveAlarm({{ $alarm->id }})"
                        >
                            Çözüldü
                        </button>

                    @else

                        <button
                            type="button"
                            class="alarm-button"
                            wire:click="reopenAlarm({{ $alarm->id }})"
                        >
                            Yeniden Aç
                        </button>

                    @endif

                </div>

            </article>

        @empty

            <div class="alarm-empty">
                Bu filtrelere uygun CRM alarmı bulunmuyor.
            </div>

        @endforelse

    </section>

</div>

</x-filament-panels::page>