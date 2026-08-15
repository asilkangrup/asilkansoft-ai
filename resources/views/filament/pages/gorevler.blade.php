


<x-filament-panels::page>

<style>

[x-cloak] {
    display: none !important;
}

.wai-tasks {
    --green: #24e17f;
    --green-dark: #087a42;
    --green-soft: #effcf5;

    --red: #d34f4f;
    --red-soft: #fff1f1;

    --orange: #a76b00;
    --orange-soft: #fff6df;

    --blue: #2d6697;
    --blue-soft: #eef6fc;

    --ink: #101712;
    --muted: #78837c;

    --line: #e5ebe7;
    --soft: #f7f9f8;
    --white: #fff;

    width: 100%;
}

/* HERO */

.tasks-hero {
    margin-bottom: 18px;
    padding: 28px 30px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 22px;

    border: 1px solid var(--line);
    border-radius: 24px;

    background:
        radial-gradient(
            circle at 90% 20%,
            rgba(36,225,127,.12),
            transparent 30%
        ),
        linear-gradient(
            145deg,
            #fff,
            #fbfdfc
        );

    box-shadow:
        0 18px 55px rgba(10,30,17,.05);
}

.tasks-eyebrow {
    display: flex;
    align-items: center;

    gap: 9px;

    color: var(--green-dark);

    font-size: 12px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.tasks-eyebrow i {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--green);
}

.tasks-hero h1 {
    margin: 8px 0 0;

    color: var(--ink);

    font-size: 30px;
    font-weight: 850;

    letter-spacing: -1px;
}

.tasks-hero p {
    max-width: 650px;

    margin: 8px 0 0;

    color: var(--muted);

    font-size: 14px;

    line-height: 1.65;
}

.tasks-live {
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

/* KPI */

.tasks-kpis {
    margin-bottom: 16px;

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0,1fr));

    gap: 12px;
}

.tasks-kpi {
    padding: 18px;

    border: 1px solid var(--line);
    border-radius: 18px;

    background: #fff;

    box-shadow:
        0 10px 30px rgba(10,30,17,.035);
}

.tasks-kpi-number {
    color: var(--ink);

    font-size: 29px;
    font-weight: 900;
}

.tasks-kpi-label {
    margin-top: 5px;

    color: var(--muted);

    font-size: 12px;
    font-weight: 700;
}

/* FILTER */

.tasks-toolbar {
    margin-bottom: 16px;

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
}

.tasks-search {
    width: 100%;
    height: 44px;

    padding: 0 13px;

    outline: none;

    border: 1px solid #dde5e0;
    border-radius: 12px;

    background: #fafcfb;

    font-size: 13px;
}

.tasks-select,
.tasks-reset {
    width: 100%;
    height: 44px;

    padding: 0 12px;

    border: 1px solid #dde5e0;
    border-radius: 12px;

    background: #fafcfb;

    font-size: 12px;
    font-weight: 700;
}

.tasks-reset {
    background: #fff;

    cursor: pointer;
}

/* GRID */

.tasks-board {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0,1fr));

    gap: 14px;
}

.task-column {
    min-height: 620px;

    overflow: hidden;

    border: 1px solid var(--line);
    border-radius: 20px;

    background: #fafcfb;

    box-shadow:
        0 12px 35px rgba(10,30,17,.035);
}

.task-column-head {
    min-height: 66px;

    padding: 0 16px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid var(--line);

    background: #fff;
}

.task-column-title {
    font-size: 13px;
    font-weight: 900;

    color: #1e2922;
}

.task-column-count {
    min-width: 30px;
    height: 30px;

    display: grid;
    place-items: center;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 900;
}

.task-column.overdue
.task-column-count {
    color: var(--red);
    background: var(--red-soft);
}

.task-column.today
.task-column-count {
    color: var(--orange);
    background: var(--orange-soft);
}

.task-column.upcoming
.task-column-count {
    color: var(--blue);
    background: var(--blue-soft);
}

.task-list {
    padding: 10px;

    display: flex;
    flex-direction: column;

    gap: 10px;
}

.task-card {
    padding: 14px;

    border: 1px solid #e1e8e3;
    border-radius: 15px;

    background: #fff;

    box-shadow:
        0 7px 20px rgba(10,30,17,.035);
}

.task-top {
    display: flex;
    align-items: center;

    gap: 10px;
}

.task-avatar {
    width: 42px;
    height: 42px;

    display: grid;
    place-items: center;

    flex: 0 0 42px;

    border-radius: 13px;

    background: var(--green-soft);

    color: var(--green-dark);

    font-size: 14px;
    font-weight: 900;
}

.task-name {
    color: #202b24;

    font-size: 12px;
    font-weight: 850;
}

.task-phone {
    margin-top: 4px;

    color: #87928b;

    font-size: 10px;
}

.task-meta {
    margin-top: 12px;

    display: flex;
    flex-wrap: wrap;

    gap: 5px;
}

.task-pill {
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

.task-time {
    margin-top: 12px;

    padding: 9px 10px;

    border-radius: 10px;

    background: #f7f9f8;

    color: #66736b;

    font-size: 10px;
    font-weight: 800;
}

.task-owner {
    margin-top: 9px;

    color: #768179;

    font-size: 9px;
}

.task-actions {
    margin-top: 12px;

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 6px;
}

.task-actions button,
.task-actions a {
    min-height: 36px;

    padding: 5px 7px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 1px solid #dfe7e2;
    border-radius: 10px;

    color: #617067;

    background: #fff;

    text-decoration: none;

    font-size: 9px;
    font-weight: 800;

    cursor: pointer;
}

.task-actions .primary {
    border-color: #ccefd9;

    color: var(--green-dark);

    background: var(--green-soft);
}

.task-empty {
    padding: 35px 15px;

    text-align: center;

    color: #919c95;

    font-size: 11px;
}

.task-toast {
    position: fixed;

    right: 24px;
    bottom: 24px;

    z-index: 99999;

    padding: 13px 16px;

    border: 1px solid #c9ecd6;
    border-radius: 13px;

    color: var(--green-dark);

    background: #f1fcf6;

    font-size: 12px;
    font-weight: 850;
}

@media (max-width: 1100px) {

    .tasks-kpis {
        grid-template-columns:
            repeat(2,1fr);
    }

    .tasks-toolbar {
        grid-template-columns:
            1fr 1fr;
    }

    .tasks-search {
        grid-column:
            1 / -1;
    }

    .tasks-board {
        grid-template-columns:
            1fr;
    }

    .task-column {
        min-height: 0;
    }
}

@media (max-width: 700px) {

    .tasks-hero {
        padding: 20px;

        display: block;

        border-radius: 19px;
    }

    .tasks-hero h1 {
        font-size: 25px;
    }

    .tasks-live {
        width: 100%;

        margin-top: 14px;

        justify-content: center;
    }

    .tasks-kpis {
        grid-template-columns:
            repeat(2,1fr);
    }

    .tasks-toolbar {
        grid-template-columns:
            1fr;
    }

    .tasks-search {
        grid-column: auto;

        font-size: 16px;
    }

    .tasks-select {
        font-size: 16px;
    }
}


/* WAI PREMIUM LIVE KPI FINAL */
.wai-live-card{position:relative;overflow:hidden;border:0!important;color:#fff!important;box-shadow:0 14px 32px rgba(15,23,42,.11)}
.wai-live-card::after{content:"";position:absolute;width:135px;height:135px;right:-58px;top:-62px;border-radius:999px;background:rgba(255,255,255,.09)}
.wai-live-card.blue{background:linear-gradient(135deg,#3b82f6,#2563eb 52%,#1d4ed8)!important}
.wai-live-card.orange{background:linear-gradient(135deg,#fb923c,#f97316 52%,#ea580c)!important}
.wai-live-card.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed 52%,#6d28d9)!important}
.wai-live-card.green{background:linear-gradient(135deg,#34d399,#10b981 52%,#059669)!important}
.wai-live-card.red{background:linear-gradient(135deg,#f87171,#ef4444 52%,#dc2626)!important}
.wai-live-card.indigo{background:linear-gradient(135deg,#6366f1,#4f46e5 52%,#4338ca)!important}
.wai-live-card.amber{background:linear-gradient(135deg,#fbbf24,#f59e0b 52%,#d97706)!important}
.wai-live-card.slate{background:linear-gradient(135deg,#64748b,#475569 52%,#334155)!important}
.wai-live-card *{position:relative;z-index:2}.wai-live-card strong,.wai-live-card span,.wai-live-card [class*="number"],.wai-live-card [class*="label"]{color:#fff!important}
.wai-live-trend{position:absolute!important;z-index:4!important;right:11px;top:11px;padding:5px 8px;border:1px solid rgba(255,255,255,.18);border-radius:999px;color:#fff!important;background:rgba(255,255,255,.13);font-size:10px!important;font-weight:900}
.wai-live-trend.up::before{content:"↗ "}.wai-live-trend.down::before{content:"↘ "}.wai-live-trend.flat::before{content:"→ "}
.wai-live-spark{position:absolute!important;z-index:3!important;right:12px;bottom:9px;width:92px;height:38px}.wai-live-spark svg{width:100%;height:100%;overflow:visible}.wai-live-spark polyline{fill:none;stroke:rgba(255,255,255,.96);stroke-width:2.1;stroke-linecap:round;stroke-linejoin:round}


/* ==========================================================================
   GÖREVLER — PREMIUM REFINED FINAL
   ========================================================================== */

.tasks-kpis .tasks-kpi {
    min-height: 165px;
    padding: 20px;
    border-radius: 22px;
}

.tasks-kpis .tasks-kpi:nth-child(1) {
    background:
        radial-gradient(circle at 92% 8%, rgba(252,165,165,.26), transparent 32%),
        linear-gradient(135deg,#f87171 0%,#ef4444 52%,#dc2626 100%) !important;
}
.tasks-kpis .tasks-kpi:nth-child(2) {
    background:
        radial-gradient(circle at 92% 8%, rgba(253,186,116,.28), transparent 32%),
        linear-gradient(135deg,#fb923c 0%,#f97316 52%,#ea580c 100%) !important;
}
.tasks-kpis .tasks-kpi:nth-child(3) {
    background:
        radial-gradient(circle at 92% 8%, rgba(147,197,253,.28), transparent 32%),
        linear-gradient(135deg,#60a5fa 0%,#3b82f6 52%,#2563eb 100%) !important;
}
.tasks-kpis .tasks-kpi:nth-child(4) {
    background:
        radial-gradient(circle at 92% 8%, rgba(196,181,253,.28), transparent 32%),
        linear-gradient(135deg,#8b5cf6 0%,#7c3aed 52%,#6d28d9 100%) !important;
}

.tasks-kpis .tasks-kpi-number {
    margin-top: 24px;
    font-size: 34px !important;
}
.tasks-kpis .tasks-kpi-label {
    margin-top: 8px;
    max-width: calc(100% - 88px);
    font-size: 13px !important;
    font-weight: 850;
}

.tasks-kpis .wai-live-spark {
    right: 14px;
    bottom: 17px;
    width: 82px;
    height: 34px;
}
.tasks-kpis .wai-live-trend {
    right: 14px;
    top: 14px;
}

/* Sadece kolon başlıkları renkli */
.task-column {
    border: 1px solid #e2e8e4 !important;
    background: #f8faf9 !important;
}
.task-card {
    background: #fff !important;
    border: 1px solid #e2e8e4 !important;
}

.task-column.overdue .task-column-head {
    background: linear-gradient(135deg,#ef4444,#dc2626) !important;
}
.task-column.today .task-column-head {
    background: linear-gradient(135deg,#f59e0b,#f97316) !important;
}
.task-column.upcoming .task-column-head {
    background: linear-gradient(135deg,#3b82f6,#2563eb) !important;
}

.task-column-head .task-column-title,
.task-column-head .task-column-count {
    color: #fff !important;
}

.task-column-head .task-column-count {
    background: rgba(255,255,255,.18) !important;
}

.task-name { font-size: 13px !important; }
.task-time,
.task-phone,
.task-owner,
.task-pill,
.task-actions button,
.task-actions a {
    font-size: 11px !important;
}


/* ==========================================================================
   GÖREVLER — FINAL COLOR ORDER
   Kırmızı / Sarı / Mavi / Yeşil
   ========================================================================== */

.tasks-kpis .tasks-kpi:nth-child(1) {
    background:
        radial-gradient(circle at 92% 8%, rgba(252,165,165,.26), transparent 32%),
        linear-gradient(135deg,#f87171 0%,#ef4444 52%,#dc2626 100%) !important;
}

.tasks-kpis .tasks-kpi:nth-child(2) {
    background:
        radial-gradient(circle at 92% 8%, rgba(253,224,71,.30), transparent 32%),
        linear-gradient(135deg,#facc15 0%,#eab308 52%,#ca8a04 100%) !important;
}

.tasks-kpis .tasks-kpi:nth-child(3) {
    background:
        radial-gradient(circle at 92% 8%, rgba(147,197,253,.28), transparent 32%),
        linear-gradient(135deg,#60a5fa 0%,#3b82f6 52%,#2563eb 100%) !important;
}

.tasks-kpis .tasks-kpi:nth-child(4) {
    background:
        radial-gradient(circle at 92% 8%, rgba(110,231,183,.28), transparent 32%),
        linear-gradient(135deg,#34d399 0%,#10b981 52%,#059669 100%) !important;
}

/* Kolon başlıkları da aynı renk mantığında */
.task-column.overdue .task-column-head {
    background: linear-gradient(135deg,#ef4444,#dc2626) !important;
}

.task-column.today .task-column-head {
    background: linear-gradient(135deg,#facc15,#eab308) !important;
}

.task-column.upcoming .task-column-head {
    background: linear-gradient(135deg,#3b82f6,#2563eb) !important;
}

/* Sarı başlıkta okunabilirlik için koyu yazı */
.task-column.today .task-column-title,
.task-column.today .task-column-count {
    color: #422006 !important;
}

.task-column.today .task-column-count {
    background: rgba(255,255,255,.32) !important;
    border: 1px solid rgba(255,255,255,.28);
}

/* Kırmızı ve mavi başlıklarda beyaz yazı */
.task-column.overdue .task-column-title,
.task-column.overdue .task-column-count,
.task-column.upcoming .task-column-title,
.task-column.upcoming .task-column-count {
    color: #fff !important;
}

.task-column.overdue .task-column-count,
.task-column.upcoming .task-column-count {
    background: rgba(255,255,255,.18) !important;
}

</style>


<div
    class="wai-tasks"

    wire:poll.60s

    x-data="{
        updated: false
    }"

    x-on:task-updated.window="
        updated = true;
        setTimeout(
            () => updated = false,
            1800
        );
    "
>

    <section class="tasks-hero">

        <div>

            <div class="tasks-eyebrow">

                <i></i>

                WAI FOLLOW-UP CENTER

            </div>

            <h1>
                Takiplerinizi kaçırmayın.
            </h1>

            <p>
                Geciken, bugün yapılacak ve yaklaşan müşteri takiplarını
                tek ekrandan yönetin.
            </p>

        </div>

        <div class="tasks-live">
            Aktif Takip Merkezi
        </div>

    </section>


        @php $live=$this->liveKpiTrends; @endphp

<section class="tasks-kpis">

        <div class="tasks-kpi wai-live-card red">
            <div class="tasks-kpi-number">
                {{ $this->overdueCount }}
            </div>
            <div class="tasks-kpi-label">
                Geciken
            </div>
        <div class="wai-live-trend {{ $live['overdue']['trend_direction'] }}">{{ $live['overdue']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['overdue']['points'] }}"/></svg></div>
</div>

        <div class="tasks-kpi wai-live-card orange">
            <div class="tasks-kpi-number">
                {{ $this->todayCount }}
            </div>
            <div class="tasks-kpi-label">
                Bugün
            </div>
        <div class="wai-live-trend {{ $live['today']['trend_direction'] }}">{{ $live['today']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['today']['points'] }}"/></svg></div>
</div>

        <div class="tasks-kpi wai-live-card blue">
            <div class="tasks-kpi-number">
                {{ $this->upcomingCount }}
            </div>
            <div class="tasks-kpi-label">
                Yaklaşan
            </div>
        <div class="wai-live-trend {{ $live['upcoming']['trend_direction'] }}">{{ $live['upcoming']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['upcoming']['points'] }}"/></svg></div>
</div>

        <div class="tasks-kpi wai-live-card purple">
            <div class="tasks-kpi-number">
                {{ $this->hotFollowUps }}
            </div>
            <div class="tasks-kpi-label">
                Sıcak Lead Takibi
            </div>
        <div class="wai-live-trend {{ $live['hot']['trend_direction'] }}">{{ $live['hot']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['hot']['points'] }}"/></svg></div>
</div>

    </section>


    <section class="tasks-toolbar">

        <input
            class="tasks-search"
            wire:model.live.debounce.350ms="search"
            placeholder="Müşteri, telefon veya firma ara..."
        >


        <select
            class="tasks-select"
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
            class="tasks-select"
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
            class="tasks-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    @php
        $columns = [
            [
                'title' => 'Geciken Takipler',
                'class' => 'overdue',
                'items' => $this->overdueTasks,
            ],
            [
                'title' => 'Bugün Yapılacaklar',
                'class' => 'today',
                'items' => $this->todayTasks,
            ],
            [
                'title' => 'Yaklaşan Takipler',
                'class' => 'upcoming',
                'items' => $this->upcomingTasks,
            ],
        ];
    @endphp


    <section class="tasks-board">

        @foreach ($columns as $column)

            <div
                class="
                    task-column
                    {{ $column['class'] }}
                "
            >

                <div class="task-column-head">

                    <div class="task-column-title">
                        {{ $column['title'] }}
                    </div>

                    <div class="task-column-count">
                        {{ $column['items']->count() }}
                    </div>

                </div>


                <div class="task-list">

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

                            $temperature =
                                $customer->lead_temperature
                                ?: 'cold';
                        @endphp


                        <article
                            class="task-card"

                            wire:key="
                                task-{{ $column['class'] }}-{{ $customer->id }}
                            "
                        >

                            <div class="task-top">

                                <div class="task-avatar">
                                    {{ $initial }}
                                </div>

                                <div>

                                    <div class="task-name">
                                        {{ $name }}
                                    </div>

                                    <div class="task-phone">
                                        {{ $customer->whatsapp_number }}
                                    </div>

                                </div>

                            </div>


                            <div class="task-meta">

                                <span
                                    class="
                                        task-pill
                                        temp-{{ $temperature }}
                                    "
                                >
                                    {{
                                        $this->temperatureLabel(
                                            $temperature
                                        )
                                    }}
                                </span>


                                <span class="task-pill channel-pill">

                                    {{
                                        $this->channelLabel(
                                            $customer->channel
                                        )
                                    }}

                                </span>

                            </div>


                            <div class="task-time">

                                {{
                                    $customer->next_follow_up_at
                                        ? $customer->next_follow_up_at
                                            ->format('d.m.Y H:i')
                                        : '-'
                                }}

                            </div>


                            <div class="task-owner">

                                Sorumlu:
                                {{
                                    $customer->assignedUser?->name
                                    ?: 'Atanmadı'
                                }}

                            </div>


                            <div class="task-actions">

                                <button
                                    type="button"
                                    class="primary"

                                    wire:click="
                                        completeTask(
                                            {{ $customer->id }}
                                        )
                                    "
                                >
                                    Tamamlandı
                                </button>


                                <button
                                    type="button"

                                    wire:click="
                                        postponeOneHour(
                                            {{ $customer->id }}
                                        )
                                    "
                                >
                                    +1 Saat
                                </button>


                                <button
                                    type="button"

                                    wire:click="
                                        postponeToTomorrow(
                                            {{ $customer->id }}
                                        )
                                    "
                                >
                                    Yarına Ertele
                                </button>


                                <a
                                    href="
                                        {{
                                            $this->customerUrl(
                                                $customer->id
                                            )
                                        }}
                                    "
                                >
                                    Müşteriyi Aç
                                </a>

                            </div>

                        </article>

                    @empty

                        <div class="task-empty">
                            Bu bölümde takip bulunmuyor.
                        </div>

                    @endforelse

                </div>

            </div>

        @endforeach

    </section>


    <div
        class="task-toast"

        x-show="updated"

        x-transition

        x-cloak
    >
        Takip güncellendi.
    </div>

</div>

</x-filament-panels::page>