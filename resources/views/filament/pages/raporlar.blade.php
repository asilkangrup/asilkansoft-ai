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

.report-alarm-panel {
    margin-bottom: 18px;
    padding: 20px;
    border: 1px solid #f0d7d7;
    border-radius: 20px;
    background:
        radial-gradient(circle at 96% 8%, rgba(235,87,87,.09), transparent 34%),
        linear-gradient(145deg, #fff, #fffafa);
    box-shadow: 0 12px 34px rgba(45,20,20,.035);
}

.report-alarm-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.report-alarm-title {
    color: #5f2e2e;
    font-size: 14px;
    font-weight: 900;
}

.report-alarm-sub {
    margin-top: 4px;
    color: #8d7777;
    font-size: 9px;
    font-weight: 750;
}

.report-alarm-link {
    min-height: 36px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #efcccc;
    border-radius: 10px;
    color: #8e3636;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    text-decoration: none;
}

.report-alarm-kpis {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 8px;
}

.report-alarm-kpi {
    padding: 11px;
    border: 1px solid #f0e2e2;
    border-radius: 12px;
    background: rgba(255,255,255,.82);
}

.report-alarm-kpi strong {
    display: block;
    color: #382323;
    font-size: 18px;
    font-weight: 900;
}

.report-alarm-kpi span {
    display: block;
    margin-top: 3px;
    color: #8b7979;
    font-size: 8px;
    font-weight: 800;
}

.report-alarm-list {
    margin-top: 12px;
    display: grid;
    gap: 7px;
}

.report-alarm-row {
    padding: 10px 11px;
    display: grid;
    grid-template-columns: auto minmax(0,1fr) auto;
    align-items: center;
    gap: 9px;
    border: 1px solid #eee5e5;
    border-radius: 11px;
    background: #fff;
}

.report-alarm-badge {
    min-height: 23px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 8px;
    font-weight: 900;
}

.report-alarm-badge.critical {
    color: #9a3030;
    background: #ffe8e8;
}

.report-alarm-badge.warning {
    color: #88610d;
    background: #fff3d3;
}

.report-alarm-customer {
    overflow: hidden;
    color: #3e3434;
    font-size: 9px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.report-alarm-open {
    color: #7e4646;
    font-size: 8px;
    font-weight: 900;
    text-decoration: none;
}

.report-alarm-empty {
    margin-top: 12px;
    padding: 16px;
    text-align: center;
    border: 1px dashed #e2d9d9;
    border-radius: 11px;
    color: #8e8585;
    background: rgba(255,255,255,.65);
    font-size: 9px;
}

@media (max-width: 700px) {
    .report-alarm-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .report-alarm-link {
        width: 100%;
    }

    .report-alarm-kpis {
        grid-template-columns: 1fr;
    }

    .report-alarm-row {
        grid-template-columns: auto minmax(0,1fr);
    }

    .report-alarm-open {
        grid-column: 1 / -1;
    }
}

.manager-summary-card {
    margin-bottom: 18px;
    padding: 22px;
    border: 1px solid #ccefd9;
    border-radius: 21px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.12), transparent 34%),
        linear-gradient(145deg, #fff, #f7fff9);
    box-shadow: 0 12px 34px rgba(10,30,17,.04);
}

.manager-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.manager-summary-title {
    color: #13452b;
    font-size: 14px;
    font-weight: 900;
}

.manager-summary-meta {
    margin-top: 4px;
    color: #7a897f;
    font-size: 9px;
    font-weight: 750;
}

.manager-summary-refresh {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #bde6cd;
    border-radius: 10px;
    color: #087a42;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.manager-summary-body {
    margin-top: 14px;
    color: #4e5e54;
    font-size: 11px;
    line-height: 1.7;
}

.manager-summary-metrics {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 8px;
}

.manager-summary-metric {
    padding: 10px;
    border: 1px solid #e4eee8;
    border-radius: 12px;
    background: rgba(255,255,255,.8);
}

.manager-summary-metric strong {
    display: block;
    color: #1e3427;
    font-size: 16px;
    font-weight: 900;
}

.manager-summary-metric span {
    display: block;
    margin-top: 3px;
    color: #7b887f;
    font-size: 8px;
    font-weight: 800;
}

@media (max-width: 900px) {
    .manager-summary-metrics {
        grid-template-columns: repeat(2,1fr);
    }
}

.sales-goal-card {
    margin-bottom: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: minmax(240px,.8fr) minmax(0,1.2fr);
    gap: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background:
        radial-gradient(circle at 92% 10%, rgba(36,225,127,.10), transparent 34%),
        linear-gradient(145deg, #fff, #fbfdfc);
}

.sales-goal-form label {
    display: block;
    margin-bottom: 7px;
    color: #5e6c63;
    font-size: 10px;
    font-weight: 900;
}

.sales-goal-input-row {
    display: flex;
    gap: 8px;
}

.sales-goal-input {
    min-width: 0;
    flex: 1;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #dfe7e2;
    border-radius: 12px;
    background: #fff;
    font-size: 13px;
    font-weight: 800;
}

.sales-goal-save {
    min-width: 90px;
    height: 44px;
    padding: 0 14px;
    border: 0;
    border-radius: 12px;
    color: #053d23;
    background: var(--green);
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
}

.sales-goal-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.sales-goal-stat {
    padding: 12px;
    border: 1px solid #edf1ee;
    border-radius: 14px;
    background: rgba(255,255,255,.78);
}

.sales-goal-value {
    color: var(--ink);
    font-size: 18px;
    font-weight: 900;
}

.sales-goal-label {
    margin-top: 4px;
    color: var(--muted);
    font-size: 9px;
    font-weight: 800;
}

.sales-goal-progress {
    grid-column: 1 / -1;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.sales-goal-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.sales-goal-percent {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #657168;
    font-size: 10px;
    font-weight: 850;
}

.sales-goal-toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    padding: 12px 16px;
    border-radius: 12px;
    color: #07512d;
    background: #e9fff2;
    border: 1px solid #bce9ce;
    font-size: 11px;
    font-weight: 850;
    box-shadow: 0 12px 32px rgba(10,30,17,.12);
}

.staff-goals-card {
    margin-bottom: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.staff-goals-head {
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.staff-goals-title {
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.staff-goals-sub {
    margin-top: 4px;
    color: var(--muted);
    font-size: 10px;
}

.staff-goals-table {
    width: 100%;
    border-collapse: collapse;
}

.staff-goals-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.staff-goals-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 10px;
    vertical-align: middle;
}

.staff-goal-rank {
    width: 30px;
    color: #8a958e;
    font-weight: 900;
}

.staff-goal-name {
    color: #202b24;
    font-weight: 900;
}

.staff-goal-input {
    width: 140px;
    height: 36px;
    padding: 0 9px;
    border: 1px solid #dde5e0;
    border-radius: 10px;
    background: #fafcfb;
    font-size: 11px;
    font-weight: 800;
}

.staff-goal-save {
    min-height: 34px;
    padding: 0 10px;
    border: 0;
    border-radius: 9px;
    color: #064526;
    background: #dff8e9;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.staff-progress-mini {
    width: 110px;
}

.staff-progress-track {
    height: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.staff-progress-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.staff-progress-label {
    margin-top: 4px;
    color: #657168;
    font-size: 9px;
    font-weight: 850;
}

.finance-kpis {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.finance-kpi {
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.07), transparent 32%),
        #fff;
}

.finance-value {
    overflow: hidden;
    color: var(--ink);
    font-size: 23px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.finance-label {
    margin-top: 6px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
}

.finance-value.positive {
    color: #087a42;
}

.finance-value.negative {
    color: #a14646;
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

.insight-grid {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.insight-card {
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.insight-title {
    margin-bottom: 16px;
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.funnel-row {
    margin-bottom: 13px;
}

.funnel-row:last-child {
    margin-bottom: 0;
}

.funnel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
}

.funnel-label {
    color: #37443b;
    font-size: 10px;
    font-weight: 850;
}

.funnel-value {
    color: #69766d;
    font-size: 9px;
    font-weight: 800;
}

.funnel-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1ee;
}

.funnel-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.lost-reason-row {
    padding: 10px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #edf1ee;
}

.lost-reason-row:last-child {
    border-bottom: 0;
}

.lost-reason-name {
    color: #3f4c44;
    font-size: 10px;
    font-weight: 800;
}

.lost-reason-count {
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #9b4545;
    background: #fff0f0;
    font-size: 9px;
    font-weight: 900;
}

.channel-revenue-card {
    margin-bottom: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.channel-revenue-table {
    width: 100%;
    border-collapse: collapse;
}

.channel-revenue-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.channel-revenue-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 10px;
}

.channel-revenue-name {
    color: #202b24;
    font-weight: 900;
}

@media (max-width: 900px) {
    .insight-grid {
        grid-template-columns: 1fr;
    }
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

    .manager-summary-card {
    margin-bottom: 18px;
    padding: 22px;
    border: 1px solid #ccefd9;
    border-radius: 21px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.12), transparent 34%),
        linear-gradient(145deg, #fff, #f7fff9);
    box-shadow: 0 12px 34px rgba(10,30,17,.04);
}

.manager-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.manager-summary-title {
    color: #13452b;
    font-size: 14px;
    font-weight: 900;
}

.manager-summary-meta {
    margin-top: 4px;
    color: #7a897f;
    font-size: 9px;
    font-weight: 750;
}

.manager-summary-refresh {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #bde6cd;
    border-radius: 10px;
    color: #087a42;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.manager-summary-body {
    margin-top: 14px;
    color: #4e5e54;
    font-size: 11px;
    line-height: 1.7;
}

.manager-summary-metrics {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 8px;
}

.manager-summary-metric {
    padding: 10px;
    border: 1px solid #e4eee8;
    border-radius: 12px;
    background: rgba(255,255,255,.8);
}

.manager-summary-metric strong {
    display: block;
    color: #1e3427;
    font-size: 16px;
    font-weight: 900;
}

.manager-summary-metric span {
    display: block;
    margin-top: 3px;
    color: #7b887f;
    font-size: 8px;
    font-weight: 800;
}

@media (max-width: 900px) {
    .manager-summary-metrics {
        grid-template-columns: repeat(2,1fr);
    }
}

.sales-goal-card {
    margin-bottom: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: minmax(240px,.8fr) minmax(0,1.2fr);
    gap: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background:
        radial-gradient(circle at 92% 10%, rgba(36,225,127,.10), transparent 34%),
        linear-gradient(145deg, #fff, #fbfdfc);
}

.sales-goal-form label {
    display: block;
    margin-bottom: 7px;
    color: #5e6c63;
    font-size: 10px;
    font-weight: 900;
}

.sales-goal-input-row {
    display: flex;
    gap: 8px;
}

.sales-goal-input {
    min-width: 0;
    flex: 1;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #dfe7e2;
    border-radius: 12px;
    background: #fff;
    font-size: 13px;
    font-weight: 800;
}

.sales-goal-save {
    min-width: 90px;
    height: 44px;
    padding: 0 14px;
    border: 0;
    border-radius: 12px;
    color: #053d23;
    background: var(--green);
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
}

.sales-goal-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.sales-goal-stat {
    padding: 12px;
    border: 1px solid #edf1ee;
    border-radius: 14px;
    background: rgba(255,255,255,.78);
}

.sales-goal-value {
    color: var(--ink);
    font-size: 18px;
    font-weight: 900;
}

.sales-goal-label {
    margin-top: 4px;
    color: var(--muted);
    font-size: 9px;
    font-weight: 800;
}

.sales-goal-progress {
    grid-column: 1 / -1;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.sales-goal-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.sales-goal-percent {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #657168;
    font-size: 10px;
    font-weight: 850;
}

.sales-goal-toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    padding: 12px 16px;
    border-radius: 12px;
    color: #07512d;
    background: #e9fff2;
    border: 1px solid #bce9ce;
    font-size: 11px;
    font-weight: 850;
    box-shadow: 0 12px 32px rgba(10,30,17,.12);
}

.staff-goals-card {
    margin-bottom: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.staff-goals-head {
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.staff-goals-title {
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.staff-goals-sub {
    margin-top: 4px;
    color: var(--muted);
    font-size: 10px;
}

.staff-goals-table {
    width: 100%;
    border-collapse: collapse;
}

.staff-goals-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.staff-goals-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 10px;
    vertical-align: middle;
}

.staff-goal-rank {
    width: 30px;
    color: #8a958e;
    font-weight: 900;
}

.staff-goal-name {
    color: #202b24;
    font-weight: 900;
}

.staff-goal-input {
    width: 140px;
    height: 36px;
    padding: 0 9px;
    border: 1px solid #dde5e0;
    border-radius: 10px;
    background: #fafcfb;
    font-size: 11px;
    font-weight: 800;
}

.staff-goal-save {
    min-height: 34px;
    padding: 0 10px;
    border: 0;
    border-radius: 9px;
    color: #064526;
    background: #dff8e9;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.staff-progress-mini {
    width: 110px;
}

.staff-progress-track {
    height: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.staff-progress-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.staff-progress-label {
    margin-top: 4px;
    color: #657168;
    font-size: 9px;
    font-weight: 850;
}

.finance-kpis {
        grid-template-columns: repeat(2,1fr);
    }
}

@media (max-width: 1000px) {
    .manager-summary-card {
    margin-bottom: 18px;
    padding: 22px;
    border: 1px solid #ccefd9;
    border-radius: 21px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.12), transparent 34%),
        linear-gradient(145deg, #fff, #f7fff9);
    box-shadow: 0 12px 34px rgba(10,30,17,.04);
}

.manager-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.manager-summary-title {
    color: #13452b;
    font-size: 14px;
    font-weight: 900;
}

.manager-summary-meta {
    margin-top: 4px;
    color: #7a897f;
    font-size: 9px;
    font-weight: 750;
}

.manager-summary-refresh {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #bde6cd;
    border-radius: 10px;
    color: #087a42;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.manager-summary-body {
    margin-top: 14px;
    color: #4e5e54;
    font-size: 11px;
    line-height: 1.7;
}

.manager-summary-metrics {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 8px;
}

.manager-summary-metric {
    padding: 10px;
    border: 1px solid #e4eee8;
    border-radius: 12px;
    background: rgba(255,255,255,.8);
}

.manager-summary-metric strong {
    display: block;
    color: #1e3427;
    font-size: 16px;
    font-weight: 900;
}

.manager-summary-metric span {
    display: block;
    margin-top: 3px;
    color: #7b887f;
    font-size: 8px;
    font-weight: 800;
}

@media (max-width: 900px) {
    .manager-summary-metrics {
        grid-template-columns: repeat(2,1fr);
    }
}

.sales-goal-card {
        grid-template-columns: 1fr;
    }

    .report-kpis {
        grid-template-columns: repeat(2,1fr);
    }

    .insight-grid {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.insight-card {
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.insight-title {
    margin-bottom: 16px;
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.funnel-row {
    margin-bottom: 13px;
}

.funnel-row:last-child {
    margin-bottom: 0;
}

.funnel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
}

.funnel-label {
    color: #37443b;
    font-size: 10px;
    font-weight: 850;
}

.funnel-value {
    color: #69766d;
    font-size: 9px;
    font-weight: 800;
}

.funnel-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1ee;
}

.funnel-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.lost-reason-row {
    padding: 10px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #edf1ee;
}

.lost-reason-row:last-child {
    border-bottom: 0;
}

.lost-reason-name {
    color: #3f4c44;
    font-size: 10px;
    font-weight: 800;
}

.lost-reason-count {
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #9b4545;
    background: #fff0f0;
    font-size: 9px;
    font-weight: 900;
}

.channel-revenue-card {
    margin-bottom: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.channel-revenue-table {
    width: 100%;
    border-collapse: collapse;
}

.channel-revenue-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.channel-revenue-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 10px;
}

.channel-revenue-name {
    color: #202b24;
    font-weight: 900;
}

@media (max-width: 900px) {
    .insight-grid {
        grid-template-columns: 1fr;
    }
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
    .sales-goal-stats {
        grid-template-columns: 1fr;
    }

    .sales-goal-progress,
    .sales-goal-percent {
        grid-column: auto;
    }

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

    .manager-summary-card {
    margin-bottom: 18px;
    padding: 22px;
    border: 1px solid #ccefd9;
    border-radius: 21px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.12), transparent 34%),
        linear-gradient(145deg, #fff, #f7fff9);
    box-shadow: 0 12px 34px rgba(10,30,17,.04);
}

.manager-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.manager-summary-title {
    color: #13452b;
    font-size: 14px;
    font-weight: 900;
}

.manager-summary-meta {
    margin-top: 4px;
    color: #7a897f;
    font-size: 9px;
    font-weight: 750;
}

.manager-summary-refresh {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #bde6cd;
    border-radius: 10px;
    color: #087a42;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.manager-summary-body {
    margin-top: 14px;
    color: #4e5e54;
    font-size: 11px;
    line-height: 1.7;
}

.manager-summary-metrics {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 8px;
}

.manager-summary-metric {
    padding: 10px;
    border: 1px solid #e4eee8;
    border-radius: 12px;
    background: rgba(255,255,255,.8);
}

.manager-summary-metric strong {
    display: block;
    color: #1e3427;
    font-size: 16px;
    font-weight: 900;
}

.manager-summary-metric span {
    display: block;
    margin-top: 3px;
    color: #7b887f;
    font-size: 8px;
    font-weight: 800;
}

@media (max-width: 900px) {
    .manager-summary-metrics {
        grid-template-columns: repeat(2,1fr);
    }
}

.sales-goal-card {
    margin-bottom: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: minmax(240px,.8fr) minmax(0,1.2fr);
    gap: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background:
        radial-gradient(circle at 92% 10%, rgba(36,225,127,.10), transparent 34%),
        linear-gradient(145deg, #fff, #fbfdfc);
}

.sales-goal-form label {
    display: block;
    margin-bottom: 7px;
    color: #5e6c63;
    font-size: 10px;
    font-weight: 900;
}

.sales-goal-input-row {
    display: flex;
    gap: 8px;
}

.sales-goal-input {
    min-width: 0;
    flex: 1;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #dfe7e2;
    border-radius: 12px;
    background: #fff;
    font-size: 13px;
    font-weight: 800;
}

.sales-goal-save {
    min-width: 90px;
    height: 44px;
    padding: 0 14px;
    border: 0;
    border-radius: 12px;
    color: #053d23;
    background: var(--green);
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
}

.sales-goal-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.sales-goal-stat {
    padding: 12px;
    border: 1px solid #edf1ee;
    border-radius: 14px;
    background: rgba(255,255,255,.78);
}

.sales-goal-value {
    color: var(--ink);
    font-size: 18px;
    font-weight: 900;
}

.sales-goal-label {
    margin-top: 4px;
    color: var(--muted);
    font-size: 9px;
    font-weight: 800;
}

.sales-goal-progress {
    grid-column: 1 / -1;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.sales-goal-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.sales-goal-percent {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #657168;
    font-size: 10px;
    font-weight: 850;
}

.sales-goal-toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    padding: 12px 16px;
    border-radius: 12px;
    color: #07512d;
    background: #e9fff2;
    border: 1px solid #bce9ce;
    font-size: 11px;
    font-weight: 850;
    box-shadow: 0 12px 32px rgba(10,30,17,.12);
}

.staff-goals-card {
    margin-bottom: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.03);
}

.staff-goals-head {
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.staff-goals-title {
    color: var(--ink);
    font-size: 14px;
    font-weight: 900;
}

.staff-goals-sub {
    margin-top: 4px;
    color: var(--muted);
    font-size: 10px;
}

.staff-goals-table {
    width: 100%;
    border-collapse: collapse;
}

.staff-goals-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #78837c;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.staff-goals-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #edf1ee;
    color: #3b473f;
    font-size: 10px;
    vertical-align: middle;
}

.staff-goal-rank {
    width: 30px;
    color: #8a958e;
    font-weight: 900;
}

.staff-goal-name {
    color: #202b24;
    font-weight: 900;
}

.staff-goal-input {
    width: 140px;
    height: 36px;
    padding: 0 9px;
    border: 1px solid #dde5e0;
    border-radius: 10px;
    background: #fafcfb;
    font-size: 11px;
    font-weight: 800;
}

.staff-goal-save {
    min-height: 34px;
    padding: 0 10px;
    border: 0;
    border-radius: 9px;
    color: #064526;
    background: #dff8e9;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.staff-progress-mini {
    width: 110px;
}

.staff-progress-track {
    height: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.staff-progress-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6df2ac, #22db79);
}

.staff-progress-label {
    margin-top: 4px;
    color: #657168;
    font-size: 9px;
    font-weight: 850;
}

.finance-kpis {
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

<div
    class="wai-reports"
    x-data="{ targetSaved: false, staffTargetSaved: false, managerSummaryRefreshed: false, managerSummaryFailed: false }"
    x-on:monthly-sales-target-saved.window="
        targetSaved = true;
        setTimeout(
            () => targetSaved = false,
            2200
        );
    "
    x-on:staff-sales-target-saved.window="
        staffTargetSaved = true;
        setTimeout(
            () => staffTargetSaved = false,
            2200
        );
    "
    x-on:manager-summary-refreshed.window="
        managerSummaryRefreshed = true;
        setTimeout(
            () => managerSummaryRefreshed = false,
            2200
        );
    "
    x-on:manager-summary-failed.window="
        managerSummaryFailed = true;
        setTimeout(
            () => managerSummaryFailed = false,
            3000
        );
    "
>

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
                Lead, satış, ciro, pipeline, dönüşüm, kanal, bot, yapay zekâ ve personel performansını
                gerçek CRM verileriniz üzerinden tek merkezden takip edin.
            </p>
        </div>

        <div class="reports-live">
            Canlı Raporlama
        </div>
    </section>


    @php
        $managerSummary =
            $this->managerSummary;

        $managerMetrics =
            is_array(
                $managerSummary?->metrics
            )
                ? $managerSummary->metrics
                : [];
    @endphp

    <section class="manager-summary-card">

        <div class="manager-summary-head">

            <div>
                <div class="manager-summary-title">
                    🤖 WAI Bugünün Yönetici Özeti
                </div>

                <div class="manager-summary-meta">
                    @if ($managerSummary?->generated_at)
                        Son güncelleme:
                        {{ $managerSummary->generated_at->format('d.m.Y H:i') }}
                    @else
                        Bugün için henüz özet oluşturulmadı.
                    @endif
                </div>
            </div>

            <button
                type="button"
                class="manager-summary-refresh"
                wire:click="refreshManagerSummary"
                wire:loading.attr="disabled"
                wire:target="refreshManagerSummary"
            >
                <span
                    wire:loading.remove
                    wire:target="refreshManagerSummary"
                >
                    Özeti Yenile
                </span>

                <span
                    wire:loading
                    wire:target="refreshManagerSummary"
                >
                    Hazırlanıyor...
                </span>
            </button>

        </div>

        <div class="manager-summary-body">
            {{
                $managerSummary?->summary
                ?: 'CRM verileri oluştuğunda WAI günlük yönetici özetini burada gösterecek.'
            }}
        </div>

        <div class="manager-summary-metrics">

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['today_leads'] ?? 0 }}
                </strong>
                <span>Bugünkü Lead</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['hot_leads'] ?? 0 }}
                </strong>
                <span>Sıcak Lead</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['priority_leads'] ?? 0 }}
                </strong>
                <span>Öncelikli Lead</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['overdue_follow_ups'] ?? 0 }}
                </strong>
                <span>Geciken Takip</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['won_today'] ?? 0 }}
                </strong>
                <span>Bugün Kazanılan</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ number_format((float) ($managerMetrics['revenue_today'] ?? 0), 2, ',', '.') }} ₺
                </strong>
                <span>Bugünkü Ciro</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ number_format((float) ($managerMetrics['open_pipeline'] ?? 0), 2, ',', '.') }} ₺
                </strong>
                <span>Açık Pipeline</span>
            </div>

            <div class="manager-summary-metric">
                <strong>
                    {{ $managerMetrics['top_lost_reason_count'] ?? 0 }}
                </strong>
                <span>
                    Kayıp Nedeni:
                    {{ $managerMetrics['top_lost_reason'] ?? 'Yok' }}
                </span>
            </div>

        </div>

    </section>


    <section class="report-alarm-panel">

        <div class="report-alarm-head">

            <div>
                <div class="report-alarm-title">
                    🚨 WAI Alarm Özeti
                </div>

                <div class="report-alarm-sub">
                    Satış ekibinin müdahale etmesi gereken aktif CRM sinyalleri.
                </div>
            </div>

            <a
                class="report-alarm-link"
                href="{{ url('/admin/alarm-merkezi') }}"
            >
                Alarm Merkezi'ne Git
            </a>

        </div>


        <div class="report-alarm-kpis">

            <div class="report-alarm-kpi">
                <strong>{{ $this->activeAlarmCount }}</strong>
                <span>Aktif Alarm</span>
            </div>

            <div class="report-alarm-kpi">
                <strong>{{ $this->criticalAlarmCount }}</strong>
                <span>Kritik Alarm</span>
            </div>

            <div class="report-alarm-kpi">
                <strong>{{ $this->resolvedAlarmTodayCount }}</strong>
                <span>Bugün Çözülen</span>
            </div>

        </div>


        @if ($this->recentCriticalAlarms->isNotEmpty())

            <div class="report-alarm-list">

                @foreach ($this->recentCriticalAlarms as $alarm)

                    @php
                        $alarmConversation =
                            $alarm->conversation;

                        $alarmCustomerName =
                            $alarmConversation?->customer_name
                            ?: $alarmConversation?->whatsapp_number
                            ?: 'Müşteri';
                    @endphp

                    <div class="report-alarm-row">

                        <span
                            class="
                                report-alarm-badge
                                {{ $alarm->severity }}
                            "
                        >
                            {{ $alarm->severityLabel() }}
                        </span>

                        <div class="report-alarm-customer">
                            {{ $alarmCustomerName }}
                            ·
                            {{ $alarm->typeLabel() }}
                        </div>

                        @if ($alarmConversation)

                            <a
                                class="report-alarm-open"
                                href="{{
                                    url(
                                        '/admin/musteriler?customer='
                                        .$alarmConversation->id
                                    )
                                }}"
                            >
                                Müşteriyi Aç
                            </a>

                        @endif

                    </div>

                @endforeach

            </div>

        @else

            <div class="report-alarm-empty">
                Şu anda aktif CRM alarmı bulunmuyor.
            </div>

        @endif

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


    @php
        $forecastComparison = $this->forecastComparison;

        $forecastDifference =
            (float) ($forecastComparison['difference'] ?? 0);

        $forecastAccuracy =
            $forecastComparison['accuracy'] ?? null;
    @endphp

    @php
        $goal =
            $this->monthlyGoalProgress;

        $goalPercent =
            min(
                100,
                (float) ($goal['percent'] ?? 0)
            );
    @endphp

    <div class="operation-title">
        Aylık Satış Hedefi
    </div>

    <section class="sales-goal-card">

        <div class="sales-goal-form">

            <label>
                Aylık Ciro Hedefiniz (₺)
            </label>

            <div class="sales-goal-input-row">

                <input
                    type="number"
                    min="0"
                    step="0.01"
                    class="sales-goal-input"
                    wire:model="monthlySalesTarget"
                    placeholder="Örn: 500000"
                >

                <button
                    type="button"
                    class="sales-goal-save"
                    wire:click="saveMonthlySalesTarget"
                    wire:loading.attr="disabled"
                    wire:target="saveMonthlySalesTarget"
                >
                    Kaydet
                </button>

            </div>

        </div>


        <div class="sales-goal-stats">

            <div class="sales-goal-stat">
                <div class="sales-goal-value">
                    {{ number_format((float) ($goal['target'] ?? 0), 2, ',', '.') }} ₺
                </div>
                <div class="sales-goal-label">
                    Aylık Hedef
                </div>
            </div>

            <div class="sales-goal-stat">
                <div class="sales-goal-value">
                    {{ number_format((float) ($goal['realized'] ?? 0), 2, ',', '.') }} ₺
                </div>
                <div class="sales-goal-label">
                    Bu Ay Gerçekleşen
                </div>
            </div>

            <div class="sales-goal-stat">
                <div class="sales-goal-value">
                    @if (($goal['completed'] ?? false))
                        +{{ number_format((float) ($goal['exceeded'] ?? 0), 2, ',', '.') }} ₺
                    @else
                        {{ number_format((float) ($goal['remaining'] ?? 0), 2, ',', '.') }} ₺
                    @endif
                </div>
                <div class="sales-goal-label">
                    {{ ($goal['completed'] ?? false) ? 'Hedef Üzeri' : 'Hedefe Kalan' }}
                </div>
            </div>

            <div class="sales-goal-progress">
                <span
                    style="width: {{ $goalPercent }}%;"
                ></span>
            </div>

            <div class="sales-goal-percent">
                <span>
                    İlerleme: %{{ number_format((float) ($goal['percent'] ?? 0), 1) }}
                </span>

                <span>
                    {{ now()->translatedFormat('F Y') }}
                </span>
            </div>

        </div>

    </section>


    <div class="operation-title">
        Satış Ekibi Hedefleri
    </div>

    <section class="staff-goals-card">

        <div class="staff-goals-head">

            <div>
                <div class="staff-goals-title">
                    Personel Aylık Hedef ve Liderlik Tablosu
                </div>

                <div class="staff-goals-sub">
                    Gerçekleşen ciro, müşterinin kazanıldığı ay ve gerçekleşen satış tutarı üzerinden hesaplanır.
                </div>
            </div>

        </div>

        <div class="report-table-scroll">

            <table class="staff-goals-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Personel</th>
                        <th>Aylık Hedef</th>
                        <th>Gerçekleşen</th>
                        <th>Kazanılan</th>
                        <th>İlerleme</th>
                        <th>Kalan / Üzeri</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($this->staffGoalRows as $row)

                        @php
                            $staffId =
                                (int) $row['staff_user_id'];

                            $staffPercent =
                                min(
                                    100,
                                    (float) $row['percent']
                                );
                        @endphp

                        <tr>

                            <td class="staff-goal-rank">
                                {{ $loop->iteration }}
                            </td>

                            <td>
                                <div class="staff-goal-name">
                                    {{ $row['name'] }}
                                </div>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="staff-goal-input"
                                    wire:model="staffSalesTargets.{{ $staffId }}"
                                    placeholder="Hedef"
                                >
                            </td>

                            <td>
                                {{ number_format((float) $row['realized'], 2, ',', '.') }} ₺
                            </td>

                            <td>
                                {{ number_format((int) $row['won_sales']) }}
                            </td>

                            <td>

                                <div class="staff-progress-mini">

                                    <div class="staff-progress-track">
                                        <span
                                            style="width: {{ $staffPercent }}%;"
                                        ></span>
                                    </div>

                                    <div class="staff-progress-label">
                                        %{{ number_format((float) $row['percent'], 1) }}
                                    </div>

                                </div>

                            </td>

                            <td>
                                @if ($row['completed'])
                                    +{{ number_format((float) $row['exceeded'], 2, ',', '.') }} ₺
                                @else
                                    {{ number_format((float) $row['remaining'], 2, ',', '.') }} ₺
                                @endif
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="staff-goal-save"
                                    wire:click="saveStaffSalesTarget({{ $staffId }})"
                                    wire:loading.attr="disabled"
                                    wire:target="saveStaffSalesTarget({{ $staffId }})"
                                >
                                    Kaydet
                                </button>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="8"
                                class="report-empty"
                            >
                                Henüz sorumlu personel atanmış CRM müşterisi bulunmuyor.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>


    <div class="operation-title">
        Finansal Performans
    </div>

    <section class="finance-kpis">

        <div class="finance-kpi">
            <div class="finance-value">
                {{ number_format($this->openPipelineValue, 2, ',', '.') }} ₺
            </div>
            <div class="finance-label">
                Açık Pipeline Değeri
            </div>
        </div>

        <div class="finance-kpi">
            <div class="finance-value">
                {{ number_format($this->weightedPipelineValue, 2, ',', '.') }} ₺
            </div>
            <div class="finance-label">
                Olasılık Ağırlıklı Pipeline
            </div>
        </div>

        <div class="finance-kpi">
            <div class="finance-value">
                {{ number_format($this->realizedRevenue, 2, ',', '.') }} ₺
            </div>
            <div class="finance-label">
                Gerçekleşen Ciro
            </div>
        </div>

        <div class="finance-kpi">
            <div class="finance-value">
                {{ number_format($this->averageSaleValue, 2, ',', '.') }} ₺
            </div>
            <div class="finance-label">
                Ortalama Satış Tutarı
            </div>
        </div>

        <div class="finance-kpi">
            <div
                class="
                    finance-value
                    {{ $forecastDifference >= 0 ? 'positive' : 'negative' }}
                "
            >
                {{ $forecastDifference >= 0 ? '+' : '' }}
                {{ number_format($forecastDifference, 2, ',', '.') }} ₺
            </div>
            <div class="finance-label">
                Tahmin / Gerçekleşen Farkı
            </div>
        </div>

        <div class="finance-kpi">
            <div class="finance-value">
                {{ $forecastAccuracy !== null ? '%' . $forecastAccuracy : '-' }}
            </div>
            <div class="finance-label">
                Tahmin Doğruluğu
                @if (($forecastComparison['count'] ?? 0) > 0)
                    · {{ $forecastComparison['count'] }} satış
                @endif
            </div>
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


    <div class="operation-title">
        Satış Analizi
    </div>

    <section class="insight-grid">

        <div class="insight-card">

            <div class="insight-title">
                Satış Hunisi
            </div>

            @foreach ($this->salesFunnel as $stage)

                <div class="funnel-row">

                    <div class="funnel-head">

                        <div class="funnel-label">
                            {{ $stage['label'] }}
                        </div>

                        <div class="funnel-value">
                            {{ $stage['count'] }}
                            ·
                            %{{ number_format($stage['percent'], 1) }}
                        </div>

                    </div>

                    <div class="funnel-track">
                        <span
                            style="width: {{ min(100, $stage['percent']) }}%;"
                        ></span>
                    </div>

                </div>

            @endforeach

        </div>


        <div class="insight-card">

            <div class="insight-title">
                En Sık Kayıp Nedenleri
            </div>

            @forelse ($this->lostReasonStats as $reason)

                <div class="lost-reason-row">

                    <div class="lost-reason-name">
                        {{ $reason->reason }}
                    </div>

                    <div class="lost-reason-count">
                        {{ $reason->total }}
                    </div>

                </div>

            @empty

                <div class="report-empty">
                    Bu dönemde kaybedilmiş lead bulunmuyor.
                </div>

            @endforelse

        </div>

    </section>


    <section class="channel-revenue-card">

        <div class="insight-title">
            Kanal Bazlı Satış ve Ciro
        </div>

        <div class="report-table-scroll">

            <table class="channel-revenue-table">

                <thead>
                    <tr>
                        <th>Kanal</th>
                        <th>Lead</th>
                        <th>Kazanılan</th>
                        <th>Dönüşüm</th>
                        <th>Gerçek Ciro</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($this->channelRevenueStats as $channel)

                        <tr>

                            <td class="channel-revenue-name">
                                {{ $channel['label'] }}
                            </td>

                            <td>
                                {{ number_format($channel['leads']) }}
                            </td>

                            <td>
                                {{ number_format($channel['sales']) }}
                            </td>

                            <td>
                                %{{ number_format($channel['conversion'], 1) }}
                            </td>

                            <td>
                                {{ number_format((float) $channel['revenue'], 2, ',', '.') }} ₺
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

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
                            <th>Pipeline</th>
                            <th>Gerçek Ciro</th>
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

                                <td>
                                    {{ number_format((float) $row->pipeline_value, 2, ',', '.') }} ₺
                                </td>

                                <td>
                                    {{ number_format((float) $row->realized_revenue, 2, ',', '.') }} ₺
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="7"
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

    <div
        class="sales-goal-toast"
        x-show="targetSaved"
        x-transition
        x-cloak
    >
        Aylık satış hedefi kaydedildi.
    </div>

    <div
        class="sales-goal-toast"
        x-show="staffTargetSaved"
        x-transition
        x-cloak
    >
        Personel satış hedefi kaydedildi.
    </div>

    <div
        class="sales-goal-toast"
        x-show="managerSummaryRefreshed"
        x-transition
        x-cloak
    >
        Yönetici özeti güncellendi.
    </div>

    <div
        class="sales-goal-toast"
        x-show="managerSummaryFailed"
        x-transition
        x-cloak
    >
        Yönetici özeti güncellenemedi.
    </div>

</div>

</x-filament-panels::page>