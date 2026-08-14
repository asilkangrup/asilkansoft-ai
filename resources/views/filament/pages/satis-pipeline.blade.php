<x-filament-panels::page>

<style>

[x-cloak] {
    display: none !important;
}

.wai-pipeline {
    --green: #24e17f;
    --green-dark: #087a42;
    --green-soft: #effcf5;

    --ink: #101712;
    --text: #3f4b43;
    --muted: #78837c;

    --line: #e5ebe7;
    --soft: #f7f9f8;
    --white: #fff;

    width: 100%;
}

/* ============================================================
   HERO
============================================================ */

.pipeline-hero {
    position: relative;
    margin-bottom: 18px;
    padding: 28px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 24px;
    background:
        radial-gradient(circle at 90% 20%, rgba(36,225,127,.12), transparent 30%),
        linear-gradient(145deg, #fff, #fbfdfc);
    box-shadow: 0 18px 55px rgba(10,30,17,.05);
}

.pipeline-eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--green-dark);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.pipeline-eyebrow i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 5px rgba(36,225,127,.10);
}

.pipeline-hero h1 {
    margin: 8px 0 0;
    color: var(--ink);
    font-size: 30px;
    font-weight: 850;
    letter-spacing: -1px;
}

.pipeline-hero p {
    max-width: 650px;
    margin: 8px 0 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.65;
}

.pipeline-live {
    min-height: 44px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #ccefd9;
    border-radius: 13px;
    color: var(--green-dark);
    background: var(--green-soft);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.pipeline-live i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
}

/* ============================================================
   KPI
============================================================ */

.pipeline-kpis {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 12px;
}

.pipeline-kpi {
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.035);
}

.pipeline-kpi-number {
    color: var(--ink);
    font-size: 29px;
    font-weight: 900;
    letter-spacing: -1px;
}

.pipeline-kpi-label {
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

/* ============================================================
   TOOLBAR
============================================================ */

.pipeline-toolbar {
    margin-bottom: 15px;
    padding: 12px;
    display: grid;
    grid-template-columns:
        minmax(280px,1fr)
        180px
        170px
        auto;
    gap: 9px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 8px 26px rgba(10,30,17,.025);
}

.pipeline-search-wrap {
    position: relative;
}

.pipeline-search-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    width: 18px;
    height: 18px;
    color: #87928b;
    transform: translateY(-50%);
}

.pipeline-search {
    width: 100%;
    height: 44px;
    padding: 0 13px 0 43px;
    outline: none;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    color: #29352d;
    background: #fafcfb;
    font-size: 13px;
}

.pipeline-select,
.pipeline-reset {
    width: 100%;
    height: 44px;
    padding: 0 12px;
    outline: none;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    color: #47544c;
    background: #fafcfb;
    font-size: 12px;
    font-weight: 700;
}

.pipeline-reset {
    background: #fff;
    cursor: pointer;
}

/* ============================================================
   BOARD
============================================================ */

.pipeline-board {
    padding-bottom: 12px;
    display: grid;
    grid-template-columns: repeat(6, minmax(260px, 1fr));
    gap: 12px;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #dce4df transparent;
}

.pipeline-column {
    min-height: 650px;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 19px;
    background: linear-gradient(180deg, #fbfcfb, #f7f9f8);
    box-shadow: 0 12px 35px rgba(10,30,17,.035);
    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;
}

.pipeline-column.drag-over {
    border-color: #75dea1;
    background:
        linear-gradient(
            180deg,
            #f2fcf6,
            #edf9f2
        );
    box-shadow:
        0 0 0 4px rgba(36,225,127,.07),
        0 16px 40px rgba(10,60,30,.08);
}

.pipeline-column-head {
    min-height: 64px;
    padding: 0 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border-bottom: 1px solid var(--line);
    background: #fff;
}

.pipeline-column-title {
    color: #1e2922;
    font-size: 13px;
    font-weight: 850;
}

.pipeline-column-count {
    min-width: 29px;
    height: 29px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    color: var(--green-dark);
    background: var(--green-soft);
    font-size: 10px;
    font-weight: 900;
}

.pipeline-cards {
    min-height: 585px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.pipeline-drop-hint {
    margin: 0 10px 10px;
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed #98dfb5;
    border-radius: 11px;
    color: #087a42;
    background: rgba(239,252,245,.82);
    font-size: 10px;
    font-weight: 850;
}

/* ============================================================
   LEAD CARD
============================================================ */

.lead-card {
    position: relative;
    padding: 14px;
    border: 1px solid #e1e8e3;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 7px 20px rgba(10,30,17,.035);
    cursor: grab;
    user-select: none;
    transition:
        transform .18s ease,
        box-shadow .18s ease,
        opacity .18s ease,
        border-color .18s ease;
}

.lead-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(10,30,17,.07);
}

.lead-card:active {
    cursor: grabbing;
}

.lead-card.dragging {
    opacity: .48;
    border-color: #7dde9f;
    transform: scale(.985);
}

.lead-drag-handle {
    position: absolute;
    right: 12px;
    top: 12px;
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    color: #91a098;
    background: #f6f8f7;
    pointer-events: none;
}

.lead-drag-handle svg {
    width: 14px;
    height: 14px;
}

.lead-top {
    padding-right: 32px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.lead-avatar {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    border: 1px solid #ccefd9;
    border-radius: 13px;
    color: var(--green-dark);
    background: var(--green-soft);
    font-size: 14px;
    font-weight: 900;
}

.lead-copy {
    min-width: 0;
}

.lead-name {
    overflow: hidden;
    color: #202b24;
    font-size: 12px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-phone {
    margin-top: 4px;
    overflow: hidden;
    color: #87928b;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-meta {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.lead-pill {
    min-height: 25px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 850;
}

.temp-hot {
    color: #b1422f;
    background: #fff0ec;
}

.temp-warm {
    color: #9e6500;
    background: #fff6de;
}

.temp-cold {
    color: #526e83;
    background: #eff5f8;
}

.channel-pill {
    color: #526159;
    background: #f1f5f2;
}

.lead-score {
    margin-top: 12px;
}

.lead-score-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.lead-score-label {
    color: #7a877f;
    font-size: 9px;
    font-weight: 800;
}

.lead-score-number {
    color: #27342c;
    font-size: 10px;
    font-weight: 900;
}

.lead-score-bar {
    height: 5px;
    margin-top: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: #ebefec;
}

.lead-score-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6bf1aa, #22da78);
}

.lead-owner {
    margin-top: 11px;
    color: #768179;
    font-size: 9px;
}

.lead-actions {
    margin-top: 12px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.lead-actions button {
    min-height: 34px;
    padding: 5px 7px;
    border: 1px solid #dfe7e2;
    border-radius: 10px;
    color: #617067;
    background: #fff;
    font-size: 9px;
    font-weight: 800;
    cursor: pointer;
}

.lead-actions button.primary {
    border-color: #ccefd9;
    color: var(--green-dark);
    background: var(--green-soft);
}

.pipeline-empty {
    padding: 30px 10px;
    text-align: center;
    color: #939d97;
    font-size: 10px;
}

/* ============================================================
   TOAST
============================================================ */

.pipeline-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 99999;
    padding: 13px 16px;
    border: 1px solid #c9ecd6;
    border-radius: 13px;
    color: var(--green-dark);
    background: #f1fcf6;
    box-shadow: 0 18px 45px rgba(7,40,21,.13);
    font-size: 12px;
    font-weight: 850;
}

/* ============================================================
   TABLET
============================================================ */

@media (max-width: 1100px) {

    .pipeline-kpis {
        grid-template-columns: repeat(2,1fr);
    }

    .pipeline-toolbar {
        grid-template-columns: 1fr 1fr;
    }

    .pipeline-search-wrap {
        grid-column: 1 / -1;
    }
}

/* ============================================================
   MOBILE
============================================================ */

@media (max-width: 700px) {

    .pipeline-hero {
        padding: 20px;
        display: block;
        border-radius: 19px;
    }

    .pipeline-hero h1 {
        font-size: 25px;
    }

    .pipeline-hero p {
        font-size: 13px;
    }

    .pipeline-live {
        width: 100%;
        margin-top: 14px;
        justify-content: center;
    }

    .pipeline-kpis {
        grid-template-columns: repeat(2,1fr);
        gap: 9px;
    }

    .pipeline-kpi {
        padding: 15px;
    }

    .pipeline-kpi-number {
        font-size: 25px;
    }

    .pipeline-toolbar {
        grid-template-columns: 1fr;
        padding: 10px;
    }

    .pipeline-search-wrap {
        grid-column: auto;
    }

    .pipeline-board {
        grid-template-columns: repeat(6, 85vw);
        gap: 10px;
        scroll-snap-type: x mandatory;
    }

    .pipeline-column {
        min-height: 520px;
        scroll-snap-align: start;
    }

    .pipeline-cards {
        min-height: 450px;
    }

    .pipeline-column-title {
        font-size: 13px;
    }

    .lead-card {
        cursor: default;
    }

    .lead-name {
        font-size: 13px;
    }

    .lead-phone {
        font-size: 11px;
    }

    .lead-drag-handle {
        display: none;
    }

    .pipeline-toast {
        left: 12px;
        right: 12px;
        bottom: 12px;
        text-align: center;
    }
}

</style>


<div
    class="wai-pipeline"

    x-data="{
        updated: false,
        draggingId: null,
        draggingFrom: null,
        overStage: null,
        busy: false,

        dragStart(id, from, event) {
            if (window.innerWidth <= 700) {
                event.preventDefault();
                return;
            }

            this.draggingId = id;
            this.draggingFrom = from;

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(id));

            requestAnimationFrame(() => {
                event.currentTarget.classList.add('dragging');
            });
        },

        dragEnd(event) {
            event.currentTarget.classList.remove('dragging');

            this.draggingId = null;
            this.draggingFrom = null;
            this.overStage = null;
        },

        async dropLead(stage) {
            if (
                ! this.draggingId
                || this.busy
                || this.draggingFrom === stage
            ) {
                this.overStage = null;
                return;
            }

            this.busy = true;

            const id = this.draggingId;

            this.overStage = null;

            try {
                await $wire.moveLead(
                    Number(id),
                    stage
                );
            } finally {
                this.busy = false;
                this.draggingId = null;
                this.draggingFrom = null;
            }
        }
    }"

    x-on:pipeline-updated.window="
        updated = true;
        setTimeout(
            () => updated = false,
            1800
        );
    "
>

    {{-- HERO --}}

    <section class="pipeline-hero">

        <div>

            <div class="pipeline-eyebrow">

                <i></i>

                WAI SALES PIPELINE

            </div>

            <h1>
                Satış fırsatlarınızı yönetin.
            </h1>

            <p>
                Lead'leri satış sürecine göre takip edin,
                sıcak müşterileri önceliklendirin ve kartları kolonlar arasında sürükleyerek taşıyın.
            </p>

        </div>


        <div class="pipeline-live">

            <i></i>

            Canlı Satış Merkezi

        </div>

    </section>


    {{-- KPI --}}

    <section class="pipeline-kpis">

        <div class="pipeline-kpi">

            <div class="pipeline-kpi-number">
                {{ $this->totalOpenLeads }}
            </div>

            <div class="pipeline-kpi-label">
                Açık Fırsat
            </div>

        </div>


        <div class="pipeline-kpi">

            <div class="pipeline-kpi-number">
                {{ $this->hotLeads }}
            </div>

            <div class="pipeline-kpi-label">
                Sıcak Lead
            </div>

        </div>


        <div class="pipeline-kpi">

            <div class="pipeline-kpi-number">
                {{ $this->proposalCount }}
            </div>

            <div class="pipeline-kpi-label">
                Teklif Aşamasında
            </div>

        </div>


        <div class="pipeline-kpi">

            <div class="pipeline-kpi-number">
                {{ $this->wonCount }}
            </div>

            <div class="pipeline-kpi-label">
                Kazanılan
            </div>

        </div>

    </section>


    {{-- TOOLBAR --}}

    <section class="pipeline-toolbar">

        <div class="pipeline-search-wrap">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <circle cx="11" cy="11" r="7"/>
                <path d="m20 20-4-4"/>
            </svg>

            <input
                class="pipeline-search"
                wire:model.live.debounce.350ms="search"
                placeholder="Müşteri, telefon veya firma ara..."
            >

        </div>


        <select
            class="pipeline-select"
            wire:model.live="temperatureFilter"
        >

            <option value="all">
                Tüm Sıcaklıklar
            </option>

            <option value="hot">
                Sıcak
            </option>

            <option value="warm">
                Ilık
            </option>

            <option value="cold">
                Soğuk
            </option>

        </select>


        <select
            class="pipeline-select"
            wire:model.live="channelFilter"
        >

            <option value="all">
                Tüm Kanallar
            </option>

            <option value="whatsapp">
                WhatsApp
            </option>

            <option value="instagram">
                Instagram
            </option>

            <option value="facebook">
                Facebook
            </option>

            <option value="web">
                Web
            </option>

        </select>


        <button
            type="button"
            class="pipeline-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    {{-- BOARD --}}

    <section class="pipeline-board">

        @php
            $columns = [
                [
                    'title' => 'Yeni Lead',
                    'status' => 'new',
                    'items' => $this->newLeads,
                    'next' => 'contacted',
                ],
                [
                    'title' => 'Görüşülüyor',
                    'status' => 'contacted',
                    'items' => $this->contactedLeads,
                    'next' => 'qualified',
                ],
                [
                    'title' => 'Nitelikli',
                    'status' => 'qualified',
                    'items' => $this->qualifiedLeads,
                    'next' => 'proposal',
                ],
                [
                    'title' => 'Teklif',
                    'status' => 'proposal',
                    'items' => $this->proposalLeads,
                    'next' => 'won',
                ],
                [
                    'title' => 'Kazanıldı',
                    'status' => 'won',
                    'items' => $this->wonLeads,
                    'next' => null,
                ],
                [
                    'title' => 'Kaybedildi',
                    'status' => 'lost',
                    'items' => $this->lostLeads,
                    'next' => null,
                ],
            ];
        @endphp


        @foreach ($columns as $column)

            <div
                class="pipeline-column"

                :class="{
                    'drag-over':
                        overStage === '{{ $column['status'] }}'
                        && draggingFrom !== '{{ $column['status'] }}'
                }"

                x-on:dragenter.prevent="
                    if (draggingId) {
                        overStage = '{{ $column['status'] }}';
                    }
                "

                x-on:dragover.prevent="
                    if (draggingId) {
                        overStage = '{{ $column['status'] }}';
                    }
                "

                x-on:dragleave="
                    if (
                        $event.currentTarget === $event.target
                        || ! $event.currentTarget.contains($event.relatedTarget)
                    ) {
                        if (overStage === '{{ $column['status'] }}') {
                            overStage = null;
                        }
                    }
                "

                x-on:drop.prevent="
                    dropLead(
                        '{{ $column['status'] }}'
                    )
                "
            >

                <div class="pipeline-column-head">

                    <div class="pipeline-column-title">
                        {{ $column['title'] }}
                    </div>

                    <div class="pipeline-column-count">
                        {{ $column['items']->count() }}
                    </div>

                </div>


                <div class="pipeline-cards">

                    <div
                        class="pipeline-drop-hint"
                        x-cloak
                        x-show="
                            draggingId
                            && overStage === '{{ $column['status'] }}'
                            && draggingFrom !== '{{ $column['status'] }}'
                        "
                    >
                        Buraya bırak → {{ $column['title'] }}
                    </div>


                    @forelse ($column['items'] as $customer)

                        @php
                            $name =
                                $customer->customer_name
                                ?: $customer->whatsapp_number;

                            $initial =
                                mb_strtoupper(
                                    mb_substr(
                                        $name,
                                        0,
                                        1
                                    )
                                );

                            $score =
                                max(
                                    0,
                                    min(
                                        100,
                                        (int) $customer->lead_score
                                    )
                                );

                            $temperature =
                                $customer->lead_temperature
                                ?: 'cold';
                        @endphp


                        <article
                            class="lead-card"

                            wire:key="
                                pipeline-{{ $column['status'] }}-{{ $customer->id }}
                            "

                            draggable="true"

                            x-on:dragstart="
                                dragStart(
                                    {{ $customer->id }},
                                    '{{ $column['status'] }}',
                                    $event
                                )
                            "

                            x-on:dragend="
                                dragEnd(
                                    $event
                                )
                            "
                        >

                            <div class="lead-drag-handle">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="9" cy="6" r="1"/>
                                    <circle cx="15" cy="6" r="1"/>
                                    <circle cx="9" cy="12" r="1"/>
                                    <circle cx="15" cy="12" r="1"/>
                                    <circle cx="9" cy="18" r="1"/>
                                    <circle cx="15" cy="18" r="1"/>
                                </svg>

                            </div>


                            <div class="lead-top">

                                <div class="lead-avatar">
                                    {{ $initial }}
                                </div>

                                <div class="lead-copy">

                                    <div class="lead-name">
                                        {{ $name }}
                                    </div>

                                    <div class="lead-phone">
                                        {{ $customer->whatsapp_number }}
                                    </div>

                                </div>

                            </div>


                            <div class="lead-meta">

                                <span
                                    class="
                                        lead-pill
                                        temp-{{ $temperature }}
                                    "
                                >
                                    {{
                                        $this->temperatureLabel(
                                            $temperature
                                        )
                                    }}
                                </span>


                                <span class="lead-pill channel-pill">

                                    {{
                                        $this->channelLabel(
                                            $customer->channel
                                        )
                                    }}

                                </span>

                            </div>


                            <div class="lead-score">

                                <div class="lead-score-head">

                                    <span class="lead-score-label">
                                        Lead Skoru
                                    </span>

                                    <span class="lead-score-number">
                                        {{ $score }}/100
                                    </span>

                                </div>


                                <div class="lead-score-bar">

                                    <span
                                        style="
                                            width:
                                            {{ $score }}%
                                        "
                                    ></span>

                                </div>

                            </div>


                            <div class="lead-owner">

                                Sorumlu:
                                {{
                                    $customer->assignedUser?->name
                                    ?: 'Atanmadı'
                                }}

                            </div>


                            <div class="lead-actions">

                                @if ($column['next'])

                                    <button
                                        type="button"
                                        class="primary"

                                        x-on:mousedown.stop
                                        x-on:dragstart.prevent

                                        wire:click="
                                            moveLead(
                                                {{ $customer->id }},
                                                '{{ $column['next'] }}'
                                            )
                                        "
                                    >
                                        İleri Taşı
                                    </button>

                                @else

                                    <button
                                        type="button"
                                        class="primary"

                                        x-on:mousedown.stop
                                        x-on:dragstart.prevent

                                        wire:click="
                                            moveLead(
                                                {{ $customer->id }},
                                                'contacted'
                                            )
                                        "
                                    >
                                        Yeniden Aç
                                    </button>

                                @endif


                                @if ($column['status'] !== 'lost')

                                    <button
                                        type="button"

                                        x-on:mousedown.stop
                                        x-on:dragstart.prevent

                                        wire:click="
                                            moveLead(
                                                {{ $customer->id }},
                                                'lost'
                                            )
                                        "
                                    >
                                        Kaybedildi
                                    </button>

                                @else

                                    <button
                                        type="button"

                                        x-on:mousedown.stop
                                        x-on:dragstart.prevent

                                        wire:click="
                                            moveLead(
                                                {{ $customer->id }},
                                                'new'
                                            )
                                        "
                                    >
                                        Yeni Lead Yap
                                    </button>

                                @endif

                            </div>

                        </article>

                    @empty

                        <div class="pipeline-empty">

                            Bu aşamada müşteri yok.

                        </div>

                    @endforelse

                </div>

            </div>

        @endforeach

    </section>


    <div
        class="pipeline-toast"

        x-show="updated"

        x-transition

        x-cloak
    >
        Satış aşaması güncellendi.
    </div>

</div>

</x-filament-panels::page>