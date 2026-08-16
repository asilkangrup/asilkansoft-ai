<x-filament-panels::page>

    <style>
        .wai-usage-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .wai-usage-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .wai-usage-card {
            position: relative;
            overflow: hidden;

            min-height: 145px;

            border-radius: 20px;
            padding: 22px;

            color: white;

            box-shadow:
                0 12px 30px rgba(15, 23, 42, .08),
                inset 0 1px 0 rgba(255, 255, 255, .12);
        }

        .wai-usage-card.blue {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );
        }

        .wai-usage-card.green {
            background:
                linear-gradient(
                    135deg,
                    #059669,
                    #047857
                );
        }

        .wai-usage-card.orange {
            background:
                linear-gradient(
                    135deg,
                    #ea580c,
                    #c2410c
                );
        }

        .wai-usage-card.red {
            background:
                linear-gradient(
                    135deg,
                    #dc2626,
                    #b91c1c
                );
        }

        .wai-usage-card::after {
            content: "";

            position: absolute;

            width: 130px;
            height: 130px;

            right: -40px;
            top: -50px;

            border-radius: 999px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );
        }

        .wai-usage-card small {
            display: block;

            font-size: 13px;
            font-weight: 700;

            opacity: .86;

            margin-bottom: 12px;
        }

        .wai-usage-card strong {
            display: block;

            font-size: 31px;
            line-height: 1.1;

            font-weight: 900;

            letter-spacing: -.03em;
        }

        .wai-usage-card span {
            display: block;

            margin-top: 10px;

            font-size: 13px;

            opacity: .84;
        }

        .wai-usage-panel {
            background:
                var(
                    --fi-color-white,
                    #fff
                );

            border:
                1px solid rgba(
                    148,
                    163,
                    184,
                    .20
                );

            border-radius: 22px;

            box-shadow:
                0 10px 35px rgba(
                    15,
                    23,
                    42,
                    .05
                );

            overflow: hidden;
        }

        .wai-usage-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 16px;

            padding: 22px 24px;

            border-bottom:
                1px solid rgba(
                    148,
                    163,
                    184,
                    .15
                );
        }

        .wai-usage-panel-head h2 {
            margin: 0;

            font-size: 19px;
            font-weight: 900;

            color:
                rgb(
                    15,
                    23,
                    42
                );
        }

        .wai-usage-panel-head p {
            margin: 5px 0 0;

            font-size: 13px;

            color:
                rgb(
                    100,
                    116,
                    139
                );
        }

        .wai-usage-table-wrap {
            overflow-x: auto;
        }

        .wai-usage-table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1200px;
        }

        .wai-usage-table th {
            padding: 14px 16px;

            text-align: left;

            font-size: 12px;
            font-weight: 800;

            color:
                rgb(
                    71,
                    85,
                    105
                );

            background:
                rgb(
                    248,
                    250,
                    252
                );

            border-bottom:
                1px solid rgba(
                    148,
                    163,
                    184,
                    .16
                );
        }

        .wai-usage-table td {
            padding: 16px;

            vertical-align: middle;

            font-size: 13px;

            color:
                rgb(
                    51,
                    65,
                    85
                );

            border-bottom:
                1px solid rgba(
                    148,
                    163,
                    184,
                    .12
                );
        }

        .wai-usage-table tr:last-child td {
            border-bottom: 0;
        }

        .wai-customer {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .wai-customer strong {
            font-size: 14px;
            font-weight: 900;

            color:
                rgb(
                    15,
                    23,
                    42
                );
        }

        .wai-customer span {
            font-size: 12px;

            color:
                rgb(
                    100,
                    116,
                    139
                );
        }

        .wai-cost-main {
            font-size: 15px;
            font-weight: 900;

            color:
                rgb(
                    15,
                    23,
                    42
                );
        }

        .wai-cost-breakdown {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;

            margin-top: 6px;
        }

        .wai-cost-pill {
            display: inline-flex;
            align-items: center;

            border-radius: 999px;

            padding: 5px 8px;

            font-size: 11px;
            font-weight: 800;
        }

        .wai-cost-pill.chat {
            background:
                rgba(
                    37,
                    99,
                    235,
                    .10
                );

            color:
                #1d4ed8;
        }

        .wai-cost-pill.finance {
            background:
                rgba(
                    234,
                    88,
                    12,
                    .10
                );

            color:
                #c2410c;
        }

        .wai-cost-pill.crm {
            background:
                rgba(
                    5,
                    150,
                    105,
                    .10
                );

            color:
                #047857;
        }

        .wai-empty {
            padding: 50px 24px;

            text-align: center;

            color:
                rgb(
                    100,
                    116,
                    139
                );
        }

        @media (max-width: 1100px) {
            .wai-usage-kpis {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }
        }

        @media (max-width: 640px) {
            .wai-usage-kpis {
                grid-template-columns: 1fr;
            }

            .wai-usage-card {
                min-height: 125px;
            }

            .wai-usage-panel-head {
                padding: 18px;
            }
        }
    </style>

    @php
        $summary = $this->monthlySummary;
        $topCustomer = $this->topCustomer;
        $rows = $this->customerRows;
    @endphp

    <div class="wai-usage-page">

        <section class="wai-usage-kpis">

            <article class="wai-usage-card blue">
                <small>
                    Bu Ay AI Çağrısı
                </small>

                <strong>
                    {{ number_format(
                        $summary['api_calls'],
                        0,
                        ',',
                        '.'
                    ) }}
                </strong>

                <span>
                    OpenAI istek sayısı
                </span>
            </article>

            <article class="wai-usage-card green">
                <small>
                    Toplam Token
                </small>

                <strong>
                    {{ $this->formatNumber(
                        $summary['total_tokens']
                    ) }}
                </strong>

                <span>
                    Input + output
                </span>
            </article>

            <article class="wai-usage-card orange">
                <small>
                    Bu Ay AI Maliyeti
                </small>

                <strong>
                    {{ $this->formatUsd(
                        $summary['cost_usd']
                    ) }}
                </strong>

                <span>
                    Tahmini OpenAI maliyeti
                </span>
            </article>

            <article class="wai-usage-card red">
                <small>
                    En Çok Harcayan
                </small>

                <strong style="font-size: 22px;">
                    {{ $topCustomer['name']
                        ?? 'Henüz veri yok' }}
                </strong>

                <span>
                    @if($topCustomer)
                        {{ $this->formatUsd(
                            $topCustomer['cost_usd']
                        ) }}
                        ·
                        {{ $this->formatNumber(
                            $topCustomer['total_tokens']
                        ) }}
                        token
                    @else
                        Kullanım kaydı bekleniyor
                    @endif
                </span>
            </article>

        </section>

        <section class="wai-usage-panel">

            <header class="wai-usage-panel-head">

                <div>
                    <h2>
                        Müşteri Bazlı AI Kullanımı
                    </h2>

                    <p>
                        {{ now()->translatedFormat(
                            'F Y'
                        ) }}
                        kullanım ve maliyet özeti
                    </p>
                </div>

            </header>

            @if($rows->isEmpty())

                <div class="wai-empty">
                    Henüz AI kullanım kaydı oluşmadı.
                    Yeni mesajlar geldikçe veriler burada görünecek.
                </div>

            @else

                <div class="wai-usage-table-wrap">

                    <table class="wai-usage-table">

                        <thead>
                            <tr>
                                <th>Müşteri</th>
                                <th>Bot</th>
                                <th>Müşteri Mesajı</th>
                                <th>AI Çağrısı</th>
                                <th>Input</th>
                                <th>Cached</th>
                                <th>Output</th>
                                <th>Toplam Token</th>
                                <th>Maliyet</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($rows as $row)

                                <tr>

                                    <td>
                                        <div class="wai-customer">
                                            <strong>
                                                {{ $row['name'] }}
                                            </strong>

                                            <span>
                                                {{ $row['email'] }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        {{ $row['bot'] }}
                                    </td>

                                    <td>
                                        {{ number_format(
                                            $row['customer_messages'],
                                            0,
                                            ',',
                                            '.'
                                        ) }}
                                    </td>

                                    <td>
                                        {{ number_format(
                                            $row['api_calls'],
                                            0,
                                            ',',
                                            '.'
                                        ) }}
                                    </td>

                                    <td>
                                        {{ $this->formatNumber(
                                            $row['input_tokens']
                                        ) }}
                                    </td>

                                    <td>
                                        {{ $this->formatNumber(
                                            $row['cached_tokens']
                                        ) }}
                                    </td>

                                    <td>
                                        {{ $this->formatNumber(
                                            $row['output_tokens']
                                        ) }}
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $this->formatNumber(
                                                $row['total_tokens']
                                            ) }}
                                        </strong>
                                    </td>

                                    <td>

                                        <div class="wai-cost-main">
                                            {{ $this->formatUsd(
                                                $row['total_cost']
                                            ) }}
                                        </div>

                                        <div class="wai-cost-breakdown">

                                            <span class="wai-cost-pill chat">
                                                Chat
                                                {{ $this->formatUsd(
                                                    $row['chat_cost']
                                                ) }}
                                            </span>

                                            @if(
                                                $row['finance_cost']
                                                > 0
                                            )
                                                <span class="wai-cost-pill finance">
                                                    Finans
                                                    {{ $this->formatUsd(
                                                        $row['finance_cost']
                                                    ) }}
                                                </span>
                                            @endif

                                            @if(
                                                $row['crm_cost']
                                                > 0
                                            )
                                                <span class="wai-cost-pill crm">
                                                    CRM
                                                    {{ $this->formatUsd(
                                                        $row['crm_cost']
                                                    ) }}
                                                </span>
                                            @endif

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </section>

    </div>

</x-filament-panels::page>