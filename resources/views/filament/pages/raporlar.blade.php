<x-filament-panels::page>

<style>
.wai-reports {
    --green: #24e17f;
    --green-dark: #087a42;
    --green-soft: #effcf5;
    --ink: #101712;
    --muted: #78837c;
    --line: #e5ebe7;
    width: 100%;
}

.reports-hero {
    margin-bottom: 18px;
    padding: 28px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    border: 1px solid var(--line);
    border-radius: 24px;
    background:
        radial-gradient(circle at 90% 20%, rgba(36,225,127,.12), transparent 30%),
        linear-gradient(145deg, #fff, #fbfdfc);
    box-shadow: 0 18px 55px rgba(10,30,17,.05);
}

.reports-eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--green-dark);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.reports-eyebrow i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
}

.reports-hero h1 {
    margin: 8px 0 0;
    color: var(--ink);
    font-size: 30px;
    font-weight: 850;
    letter-spacing: -1px;
}

.reports-hero p {
    max-width: 680px;
    margin: 8px 0 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.65;
}

.reports-live {
    min-height: 44px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    border: 1px solid #ccefd9;
    border-radius: 13px;
    color: var(--green-dark);
    background: var(--green-soft);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.reports-toolbar {
    margin-bottom: 16px;
    padding: 12px;
    display: grid;
    grid-template-columns: 180px 180px minmax(200px,1fr) auto;
    gap: 9px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
}

.reports-select,
.reports-reset {
    width: 100%;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    background: #fafcfb;
    font-size: 12px;
    font-weight: 700;
}

.reports-reset {
    background: #fff;
    cursor: pointer;
}

.report-kpis {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 12px;
}

.report-kpi {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.035);
}

.report-kpi-number {
    overflow: hidden;
    color: var(--ink);
    font-size: 29px;
    font-weight: 900;
    letter-spacing: -1px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.report-kpi-label {
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

.operation-title {
    margin: 26px 0 12px;
    color: #5e6c63;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.operation-kpis {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(6, minmax(0,1fr));
    gap: 10px;
}

.operation-kpi {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(145deg, #fff, #fbfdfc);
}

.operation-value {
    overflow: hidden;
    color: var(--ink);
    font-size: 22px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operation-label {
    margin-top: 6px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 750;
}

.operation-sub {
    margin-top: 6px;
    color: #98a199;
    font-size: 9px;
}

.report-grid {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 14px;
}

.report-card {
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 12px 35px rgba(10,30,17,.035);
}

.report-card-title {
    margin-bottom: 18px;
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.trend-chart {
    height: 230px;
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.trend-item {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    height: 100%;
}

.trend-value {
    margin-bottom: 7px;
    text-align: center;
    color: #536058;
    font-size: 10px;
    font-weight: 850;
}

.trend-bar-wrap {
    height: 175px;
    display: flex;
    align-items: flex-end;
}

.trend-bar {
    width: 100%;
    min-height: 4px;
    border-radius: 8px 8px 3px 3px;
    background: linear-gradient(180deg, #67efaa, #20d976);
}

.trend-label {
    margin-top: 8px;
    text-align: center;
    color: #8b968f;
    font-size: 9px;
}

.channel-row {
    margin-bottom: 16px;
}

.channel-row:last-child {
    margin-bottom: 0;
}

.channel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 7px;
}

.channel-name {
    color: #37443b;
    font-size: 11px;
    font-weight: 800;
}

.channel-value {
    color: #69766d;
    font-size: 10px;
    font-weight: 800;
}

.channel-bar {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1ee;
}

.channel-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #67efaa, #20d976);
}

.report-wide {
    grid-column: 1 / -1;
}

.ai-human-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.ai-human-box {
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fafcfb;
}

.ai-human-box strong {
    display: block;
    color: var(--ink);
    font-size: 25px;
    font-weight: 900;
}

.ai-human-box span {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 750;
}

.ratio-track {
    height: 12px;
    margin-top: 18px;
    overflow: hidden;
    display: flex;
    border-radius: 999px;
    background: #edf1ee;
}

.ratio-ai {
    height: 100%;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.ratio-human {
    height: 100%;
    background: linear-gradient(90deg, #9ca9a1, #65736b);
}

.ratio-legend {
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #7b877f;
    font-size: 9px;
    font-weight: 800;
}

.bot-table,
.staff-table {
    width: 100%;
    border-collapse: collapse;
}

.bot-table th,
.staff-table th {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
}

.bot-table td,
.staff-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 11px;
}

.bot-name,
.staff-name {
    font-weight: 850;
    color: #202b24;
}

.performance-rate {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 54px;
    min-height: 27px;
    padding: 0 8px;
    border-radius: 999px;
    color: var(--green-dark);
    background: var(--green-soft);
    font-size: 9px;
    font-weight: 900;
}

.report-empty {
    padding: 30px;
    text-align: center;
    color: #8b968f;
    font-size: 12px;
}

@media (max-width: 1250px) {
    .operation-kpis {
        grid-template-columns: repeat(3,1fr);
    }
}

@media (max-width: 1000px) {
    .report-kpis {
        grid-template-columns: repeat(2,1fr);
    }

    .report-grid {
        grid-template-columns: 1fr;
    }

    .report-wide {
        grid-column: auto;
    }

    .reports-toolbar {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 700px) {
    .reports-hero {
        padding: 20px;
        display: block;
        border-radius: 19px;
    }

    .reports-hero h1 {
        font-size: 25px;
    }

    .reports-live {
        width: 100%;
        margin-top: 14px;
        justify-content: center;
    }

    .report-kpis {
        grid-template-columns: repeat(2,1fr);
    }

    .operation-kpis {
        grid-template-columns: repeat(2,1fr);
    }

    .reports-toolbar {
        grid-template-columns: 1fr;
    }

    .reports-select {
        font-size: 16px;
    }

    .report-card {
        padding: 15px;
    }

    .trend-chart {
        gap: 5px;
    }

    .ai-human-grid {
        grid-template-columns: 1fr;
    }

    .bot-table,
    .staff-table {
        min-width: 650px;
    }

    .report-table-scroll {
        overflow-x: auto;
    }
}
</style>

<div class="wai-reports">

    <section class="reports-hero">
        <div>
            <div class="reports-eyebrow">
                <i></i>
                WAI ANALYTICS
            </div>

            <h1>
                Satış ve operasyon performansınızı görün.
            </h1>

            <p>
                Lead, satış, dönüşüm, kanal, bot, yapay zekâ ve personel performansını
                gerçek CRM verileriniz üzerinden tek merkezden takip edin.
            </p>
        </div>

        <div class="reports-live">
            Canlı Raporlama
        </div>
    </section>


    <section class="reports-toolbar">

        <select class="reports-select" wire:model.live="period">
            <option value="today">Bugün</option>
            <option value="7">Son 7 Gün</option>
            <option value="30">Son 30 Gün</option>
            <option value="90">Son 90 Gün</option>
            <option value="all">Tüm Zamanlar</option>
        </select>

        <select class="reports-select" wire:model.live="channelFilter">
            <option value="all">Tüm Kanallar</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="instagram">Instagram</option>
            <option value="facebook">Facebook</option>
            <option value="web">Web</option>
        </select>

        <select class="reports-select" wire:model.live="botFilter">
            <option value="all">Tüm Botlar</option>

            @foreach ($this->bots as $bot)
                <option value="{{ $bot->id }}">
                    {{ $bot->name }}
                </option>
            @endforeach
        </select>

        <button
            type="button"
            class="reports-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    <section class="report-kpis">

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->totalLeads }}</div>
            <div class="report-kpi-label">Toplam Lead</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->openLeads }}</div>
            <div class="report-kpi-label">Açık Fırsat</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->hotLeads }}</div>
            <div class="report-kpi-label">Sıcak Lead</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->proposalLeads }}</div>
            <div class="report-kpi-label">Teklif</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->wonLeads }}</div>
            <div class="report-kpi-label">Kazanılan</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->lostLeads }}</div>
            <div class="report-kpi-label">Kaybedilen</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">
                %{{ number_format($this->conversionRate, 1) }}
            </div>
            <div class="report-kpi-label">Dönüşüm Oranı</div>
        </div>

        <div class="report-kpi">
            <div class="report-kpi-number">{{ $this->todayLeads }}</div>
            <div class="report-kpi-label">Bugün Gelen Lead</div>
        </div>

    </section>


    <div class="operation-title">
        AI & Operasyon Performansı
    </div>

    <section class="operation-kpis">

        <div class="operation-kpi">
            <div class="operation-value">
                %{{ number_format($this->aiResponseRate, 1) }}
            </div>
            <div class="operation-label">AI Yanıt Oranı</div>
            <div class="operation-sub">
                {{ $this->aiMessageCount }} AI mesajı
            </div>
        </div>

        <div class="operation-kpi">
            <div class="operation-value">
                %{{ number_format($this->humanResponseRate, 1) }}
            </div>
            <div class="operation-label">İnsan Yanıt Oranı</div>
            <div class="operation-sub">
                {{ $this->humanMessageCount }} insan mesajı
            </div>
        </div>

        <div class="operation-kpi">
            <div class="operation-value">
                {{ $this->averageResponseLabel }}
            </div>
            <div class="operation-label">Ortalama İlk Cevap</div>
            <div class="operation-sub">Müşteri → ilk cevap</div>
        </div>

        <div class="operation-kpi">
            <div class="operation-value">
                {{ $this->averageAiResponseLabel }}
            </div>
            <div class="operation-label">AI Cevap Süresi</div>
            <div class="operation-sub">Ortalama AI yanıtı</div>
        </div>

        <div class="operation-kpi">
            <div class="operation-value">
                {{ $this->averageHumanResponseLabel }}
            </div>
            <div class="operation-label">İnsan Cevap Süresi</div>
            <div class="operation-sub">Ortalama personel yanıtı</div>
        </div>

        <div class="operation-kpi">
            <div class="operation-value">
                {{ $this->takeoverCount }}
            </div>
            <div class="operation-label">İnsan Devralma</div>
            <div class="operation-sub">
                Şu an aktif: {{ $this->activeHumanTakeovers }}
            </div>
        </div>

    </section>


    <section class="report-grid">

        <div class="report-card">

            <div class="report-card-title">
                Son 7 Gün Lead Trendi
            </div>

            @php
                $trend = $this->sevenDayTrend;

                $maxTrend = max(
                    1,
                    collect($trend)->max('count')
                );
            @endphp

            <div class="trend-chart">

                @foreach ($trend as $item)

                    @php
                        $height = max(
                            3,
                            round(
                                ($item['count'] / $maxTrend) * 100
                            )
                        );
                    @endphp

                    <div class="trend-item">

                        <div class="trend-value">
                            {{ $item['count'] }}
                        </div>

                        <div class="trend-bar-wrap">
                            <div
                                class="trend-bar"
                                style="height: {{ $height }}%;"
                            ></div>
                        </div>

                        <div class="trend-label">
                            {{ $item['label'] }}
                        </div>

                    </div>

                @endforeach

            </div>

        </div>


        <div class="report-card">

            <div class="report-card-title">
                Kanal Dağılımı
            </div>

            @foreach ($this->channelStats as $channel)

                <div class="channel-row">

                    <div class="channel-head">
                        <div class="channel-name">
                            {{ $channel['label'] }}
                        </div>

                        <div class="channel-value">
                            {{ $channel['count'] }}
                            ·
                            %{{ number_format($channel['percent'], 1) }}
                        </div>
                    </div>

                    <div class="channel-bar">
                        <span
                            style="width: {{ min(100, $channel['percent']) }}%;"
                        ></span>
                    </div>

                </div>

            @endforeach

        </div>


        <div class="report-card report-wide">

            <div class="report-card-title">
                Yapay Zekâ / İnsan Yanıt Dağılımı
            </div>

            <div class="ai-human-grid">

                <div class="ai-human-box">
                    <strong>{{ number_format($this->aiMessageCount) }}</strong>
                    <span>Yapay zekâ tarafından gönderilen mesaj</span>
                </div>

                <div class="ai-human-box">
                    <strong>{{ number_format($this->humanMessageCount) }}</strong>
                    <span>Personel tarafından gönderilen mesaj</span>
                </div>

            </div>

            <div class="ratio-track">

                <div
                    class="ratio-ai"
                    style="width: {{ min(100, $this->aiResponseRate) }}%;"
                ></div>

                <div
                    class="ratio-human"
                    style="width: {{ min(100, $this->humanResponseRate) }}%;"
                ></div>

            </div>

            <div class="ratio-legend">
                <span>AI %{{ number_format($this->aiResponseRate, 1) }}</span>
                <span>İnsan %{{ number_format($this->humanResponseRate, 1) }}</span>
            </div>

        </div>


        <div class="report-card report-wide">

            <div class="report-card-title">
                Personel Performansı
            </div>

            <div class="report-table-scroll">

                <table class="staff-table">

                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>İnsan Mesajı</th>
                            <th>Atanan Lead</th>
                            <th>Açık Lead</th>
                            <th>Kazanılan</th>
                            <th>Dönüşüm</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($this->staffPerformance as $staff)

                            <tr>
                                <td class="staff-name">
                                    {{ $staff['name'] }}
                                </td>

                                <td>
                                    {{ number_format($staff['human_messages']) }}
                                </td>

                                <td>
                                    {{ number_format($staff['assigned_leads']) }}
                                </td>

                                <td>
                                    {{ number_format($staff['open_leads']) }}
                                </td>

                                <td>
                                    {{ number_format($staff['won_leads']) }}
                                </td>

                                <td>
                                    <span class="performance-rate">
                                        %{{ number_format($staff['conversion_rate'], 1) }}
                                    </span>
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="6"
                                    class="report-empty"
                                >
                                    Bu tarih aralığında personel performans verisi bulunmuyor.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        <div class="report-card report-wide">

            <div class="report-card-title">
                Bot Performansı
            </div>

            <div class="report-table-scroll">

                <table class="bot-table">

                    <thead>
                        <tr>
                            <th>Bot</th>
                            <th>Lead</th>
                            <th>Sıcak Lead</th>
                            <th>Kazanılan</th>
                            <th>Dönüşüm</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($this->botStats as $row)

                            @php
                                $rate =
                                    (int) $row->total_leads > 0
                                        ? round(
                                            (
                                                (int) $row->won_leads
                                                /
                                                (int) $row->total_leads
                                            )
                                            * 100,
                                            1
                                        )
                                        : 0;
                            @endphp

                            <tr>
                                <td class="bot-name">
                                    {{
                                        $row->aiBot?->name
                                        ?: 'Silinmiş Bot'
                                    }}
                                </td>

                                <td>{{ $row->total_leads }}</td>
                                <td>{{ $row->hot_leads }}</td>
                                <td>{{ $row->won_leads }}</td>
                                <td>%{{ number_format($rate, 1) }}</td>
                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="5"
                                    class="report-empty"
                                >
                                    Bu tarih aralığında rapor verisi bulunmuyor.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </section>

</div>

</x-filament-panels::page>