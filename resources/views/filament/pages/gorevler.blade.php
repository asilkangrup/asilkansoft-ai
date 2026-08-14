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

</style>


<div
    class="wai-tasks"

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


    <section class="tasks-kpis">

        <div class="tasks-kpi">
            <div class="tasks-kpi-number">
                {{ $this->overdueCount }}
            </div>
            <div class="tasks-kpi-label">
                Geciken
            </div>
        </div>

        <div class="tasks-kpi">
            <div class="tasks-kpi-number">
                {{ $this->todayCount }}
            </div>
            <div class="tasks-kpi-label">
                Bugün
            </div>
        </div>

        <div class="tasks-kpi">
            <div class="tasks-kpi-number">
                {{ $this->upcomingCount }}
            </div>
            <div class="tasks-kpi-label">
                Yaklaşan
            </div>
        </div>

        <div class="tasks-kpi">
            <div class="tasks-kpi-number">
                {{ $this->hotFollowUps }}
            </div>
            <div class="tasks-kpi-label">
                Sıcak Lead Takibi
            </div>
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