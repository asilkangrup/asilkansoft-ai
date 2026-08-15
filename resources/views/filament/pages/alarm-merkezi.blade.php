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
    grid-template-columns:repeat(4,minmax(0,1fr));
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
    grid-template-columns:160px 160px 190px minmax(220px,1fr) auto;
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

.risk-customers-card {
    margin-bottom: 14px;
    padding: 18px;
    border: 1px solid #eddada;
    border-radius: 17px;
    background:
        radial-gradient(circle at 96% 8%, rgba(225,80,80,.07), transparent 35%),
        #fff;
}

.risk-customers-head {
    margin-bottom: 13px;
}

.risk-customers-title {
    color: #512f2f;
    font-size: 13px;
    font-weight: 900;
}

.risk-customers-sub {
    margin-top: 3px;
    color: #8c7777;
    font-size: 9px;
}

.risk-customer-list {
    display: grid;
    gap: 8px;
}

.risk-customer-row {
    padding: 11px;
    display: grid;
    grid-template-columns: 54px minmax(0,1fr) auto;
    align-items: center;
    gap: 10px;
    border: 1px solid #eee3e3;
    border-radius: 12px;
    background: #fff;
}

.risk-score-box {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: #8f2c2c;
    background: #ffe9e9;
    font-size: 13px;
    font-weight: 900;
}

.risk-customer-name {
    color: #302626;
    font-size: 10px;
    font-weight: 900;
}

.risk-customer-meta {
    margin-top: 4px;
    color: #8a7e7e;
    font-size: 8px;
    line-height: 1.45;
}

.risk-customer-action {
    margin-top: 5px;
    color: #5a4b4b;
    font-size: 9px;
    line-height: 1.45;
}

.risk-customer-open {
    min-height: 34px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #efcccc;
    border-radius: 9px;
    color: #8a3333;
    background: #fff;
    font-size: 8px;
    font-weight: 900;
    text-decoration: none;
}

@media(max-width:800px) {
    .risk-customer-row {
        grid-template-columns: 50px minmax(0,1fr);
    }

    .risk-customer-open {
        grid-column: 1 / -1;
    }
}

.alarm-staff-card {
    margin-bottom: 14px;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background: #fff;
}

.alarm-staff-head {
    margin-bottom: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.alarm-staff-title {
    color: var(--ink);
    font-size: 13px;
    font-weight: 900;
}

.alarm-staff-sub {
    margin-top: 3px;
    color: var(--muted);
    font-size: 9px;
}

.alarm-staff-table {
    width: 100%;
    border-collapse: collapse;
}

.alarm-staff-table th {
    padding: 9px 10px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    color: #7d8981;
    font-size: 8px;
    font-weight: 900;
    text-transform: uppercase;
}

.alarm-staff-table td {
    padding: 10px;
    border-bottom: 1px solid #edf1ee;
    color: #465248;
    font-size: 9px;
}

.alarm-staff-name {
    color: #26332a;
    font-weight: 900;
}

.alarm-staff-danger {
    color: #9b3030;
    font-weight: 900;
}

.alarm-staff-good {
    color: #087a42;
    font-weight: 900;
}

.alarm-staff-empty {
    padding: 22px;
    text-align: center;
    color: #8b968f;
    font-size: 10px;
}

@media(max-width:800px) {
    .alarm-staff-scroll {
        overflow-x: auto;
    }

    .alarm-staff-table {
        min-width: 700px;
    }
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

.alarm-priority {
    min-height: 25px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    color: #334238;
    background: #eef3f0;
    font-size: 8px;
    font-weight: 900;
}

.alarm-priority.urgent {
    color: #8f2525;
    background: #ffdede;
}

.alarm-priority.very-high {
    color: #9a4f12;
    background: #ffe9d2;
}

.alarm-priority.high {
    color: #7d6710;
    background: #fff4c8;
}

.alarm-action-box {
    margin-top: 11px;
    padding: 11px 12px;
    border: 1px solid #dce9e1;
    border-radius: 11px;
    background: #f8fcfa;
}

.alarm-action-title {
    color: #41604d;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.alarm-action-text {
    margin-top: 5px;
    color: #506158;
    font-size: 10px;
    line-height: 1.55;
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

.alarm-snooze-box {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #e2e8e4;
    border-radius: 11px;
    background: #fafcfb;
}

.alarm-snooze-title {
    margin-bottom: 7px;
    color: #68756d;
    font-size: 8px;
    font-weight: 900;
    text-transform: uppercase;
}

.alarm-snooze-buttons {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 5px;
}

.alarm-snooze-button {
    min-height: 30px;
    padding: 0 6px;
    border: 1px solid #dce4df;
    border-radius: 8px;
    color: #526057;
    background: #fff;
    font-size: 8px;
    font-weight: 850;
    cursor: pointer;
}

.alarm-custom-snooze {
    margin-top: 6px;
    display: grid;
    grid-template-columns: minmax(0,1fr) auto;
    gap: 5px;
}

.alarm-custom-input {
    min-width: 0;
    height: 32px;
    padding: 0 7px;
    border: 1px solid #dce4df;
    border-radius: 8px;
    background: #fff;
    font-size: 9px;
}

.alarm-snoozed-info {
    margin-top: 10px;
    padding: 8px 10px;
    border-radius: 9px;
    color: #765c13;
    background: #fff6d9;
    font-size: 9px;
    font-weight: 800;
}

.alarm-sla {
    min-height: 25px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    color: #496056;
    background: #edf3ef;
    font-size: 8px;
    font-weight: 900;
}

.alarm-sla.breached {
    color: #991f1f;
    background: #ffdede;
    animation: waiAlarmPulse 1.8s ease-in-out infinite;
}

@keyframes waiAlarmPulse {
    0%, 100% {
        opacity: 1;
    }

    50% {
        opacity: .68;
    }
}

.alarm-sla-summary {
    margin-bottom: 14px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.alarm-sla-card {
    padding: 14px;
    border: 1px solid #e2e8e4;
    border-radius: 14px;
    background: #fff;
}

.alarm-sla-card strong {
    display: block;
    color: #26352c;
    font-size: 20px;
    font-weight: 900;
}

.alarm-sla-card span {
    display: block;
    margin-top: 4px;
    color: #7a877f;
    font-size: 9px;
    font-weight: 800;
}

.alarm-sla-card.danger {
    border-color: #f1cccc;
    background: #fffafa;
}

.alarm-sla-card.danger strong {
    color: #9a3030;
}

@media(max-width:800px) {
    .alarm-sla-summary {
        grid-template-columns: 1fr;
    }
}

.alarm-history-toggle {
    margin-top: 9px;
    min-height: 32px;
    padding: 0 10px;
    border: 1px solid #e0e7e2;
    border-radius: 9px;
    color: #526159;
    background: #fff;
    font-size: 8px;
    font-weight: 900;
    cursor: pointer;
}

.alarm-history {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #e6ebe8;
    border-radius: 11px;
    background: #fbfcfb;
}

.alarm-history-item {
    position: relative;
    padding: 9px 9px 9px 17px;
    border-left: 2px solid #dce5df;
}

.alarm-history-item:last-child {
    padding-bottom: 2px;
}

.alarm-history-item::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 13px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #7b8b81;
}

.alarm-history-title {
    color: #39473e;
    font-size: 9px;
    font-weight: 900;
}

.alarm-history-meta {
    margin-top: 3px;
    color: #8a958e;
    font-size: 8px;
}

.alarm-history-description {
    margin-top: 4px;
    color: #68756d;
    font-size: 9px;
    line-height: 1.45;
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

/* ==========================================================================
   WAI READABILITY STANDARD
   Ana metin >= 12px, yardımcı metin >= 11px.
   ========================================================================== */

.wai-alarm-center {
    font-size: 13px;
}

.wai-alarm-center .alarm-kpi span,
.wai-alarm-center .alarm-sla-card span,
.wai-alarm-center .alarm-staff-sub,
.wai-alarm-center .risk-customers-sub,
.wai-alarm-center .alarm-action-title,
.wai-alarm-center .alarm-history-meta,
.wai-alarm-center .alarm-snooze-title {
    font-size: 11px !important;
    line-height: 1.45;
}

.wai-alarm-center .alarm-badge,
.wai-alarm-center .alarm-priority,
.wai-alarm-center .alarm-sla,
.wai-alarm-center .alarm-type,
.wai-alarm-center .alarm-button,
.wai-alarm-center .alarm-snooze-button,
.wai-alarm-center .alarm-history-toggle,
.wai-alarm-center .risk-customer-open {
    font-size: 11px !important;
    line-height: 1.3;
}

.wai-alarm-center .alarm-message,
.wai-alarm-center .alarm-action-text,
.wai-alarm-center .alarm-history-description,
.wai-alarm-center .risk-customer-meta,
.wai-alarm-center .risk-customer-action,
.wai-alarm-center .alarm-meta {
    font-size: 12px !important;
    line-height: 1.6;
}

.wai-alarm-center .alarm-title,
.wai-alarm-center .risk-customer-name,
.wai-alarm-center .alarm-staff-title {
    font-size: 14px !important;
    line-height: 1.4;
}

.wai-alarm-center .alarm-staff-table th {
    font-size: 11px !important;
}

.wai-alarm-center .alarm-staff-table td {
    font-size: 12px !important;
}

.wai-alarm-center .alarm-select,
.wai-alarm-center input,
.wai-alarm-center select,
.wai-alarm-center button {
    font-size: 12px;
}

@media (max-width: 800px) {
    .wai-alarm-center .alarm-message,
    .wai-alarm-center .alarm-action-text,
    .wai-alarm-center .risk-customer-meta,
    .wai-alarm-center .risk-customer-action {
        font-size: 12px !important;
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
   ALARM MERKEZİ — PREMIUM REFINED FINAL
   ========================================================================== */

.alarm-kpis .alarm-kpi {
    min-height: 155px;
    padding: 20px;
    border-radius: 22px;
}

.alarm-kpis .alarm-kpi:nth-child(1) {
    background:
        radial-gradient(circle at 92% 8%, rgba(252,165,165,.25), transparent 32%),
        linear-gradient(135deg,#f87171 0%,#ef4444 52%,#dc2626 100%) !important;
}
.alarm-kpis .alarm-kpi:nth-child(2) {
    background:
        radial-gradient(circle at 92% 8%, rgba(253,186,116,.27), transparent 32%),
        linear-gradient(135deg,#fb923c 0%,#f97316 52%,#ea580c 100%) !important;
}
.alarm-kpis .alarm-kpi:nth-child(3) {
    background:
        radial-gradient(circle at 92% 8%, rgba(110,231,183,.26), transparent 32%),
        linear-gradient(135deg,#34d399 0%,#10b981 52%,#059669 100%) !important;
}
.alarm-kpis .alarm-kpi:nth-child(4) {
    background:
        radial-gradient(circle at 92% 8%, rgba(165,180,252,.27), transparent 32%),
        linear-gradient(135deg,#6366f1 0%,#4f46e5 52%,#4338ca 100%) !important;
}

.alarm-kpis .alarm-kpi strong {
    margin-top: 26px;
    font-size: 33px !important;
}
.alarm-kpis .alarm-kpi span {
    font-size: 12px !important;
}

.alarm-kpis .wai-live-spark {
    right: 14px;
    bottom: 14px;
    width: 78px;
    height: 32px;
}

/* SLA kartları beyaz + üst vurgu */
.alarm-sla-card {
    border: 1px solid #e2e8e4 !important;
    background: #fff !important;
    box-shadow: 0 9px 24px rgba(15,23,42,.04);
}
.alarm-sla-card:nth-child(1) { border-top: 4px solid #ef4444 !important; }
.alarm-sla-card:nth-child(2) { border-top: 4px solid #3b82f6 !important; }
.alarm-sla-card:nth-child(3) { border-top: 4px solid #f59e0b !important; }

.alarm-sla-card strong { color: #111a14 !important; }
.alarm-sla-card span { color: #748078 !important; }

/* Risk ve personel kartları beyaz */
.risk-customers-card,
.alarm-staff-card {
    background: #fff !important;
    box-shadow: 0 9px 24px rgba(15,23,42,.04);
}
.risk-customers-card { border: 1px solid #f0caca !important; }
.alarm-staff-card { border: 1px solid #d8e4f0 !important; }

.risk-customers-title { color: #b91c1c !important; }
.alarm-staff-title { color: #1d4ed8 !important; }


/* ==========================================================================
   ALARM MERKEZİ — FINAL COLOR ORDER
   Kırmızı / Sarı / Yeşil / Mavi
   ========================================================================== */

.alarm-kpis .alarm-kpi:nth-child(1) {
    background:
        radial-gradient(circle at 92% 8%, rgba(252,165,165,.26), transparent 32%),
        linear-gradient(135deg,#f87171 0%,#ef4444 52%,#dc2626 100%) !important;
}

.alarm-kpis .alarm-kpi:nth-child(2) {
    background:
        radial-gradient(circle at 92% 8%, rgba(253,224,71,.30), transparent 32%),
        linear-gradient(135deg,#facc15 0%,#eab308 52%,#ca8a04 100%) !important;
}

.alarm-kpis .alarm-kpi:nth-child(3) {
    background:
        radial-gradient(circle at 92% 8%, rgba(110,231,183,.28), transparent 32%),
        linear-gradient(135deg,#34d399 0%,#10b981 52%,#059669 100%) !important;
}

.alarm-kpis .alarm-kpi:nth-child(4) {
    background:
        radial-gradient(circle at 92% 8%, rgba(147,197,253,.28), transparent 32%),
        linear-gradient(135deg,#60a5fa 0%,#3b82f6 52%,#2563eb 100%) !important;
}

/* Sarı kritik kartta koyu yazı */
.alarm-kpis .alarm-kpi:nth-child(2) strong,
.alarm-kpis .alarm-kpi:nth-child(2) span,
.alarm-kpis .alarm-kpi:nth-child(2) .wai-live-trend {
    color: #422006 !important;
}

.alarm-kpis .alarm-kpi:nth-child(2) .wai-live-trend {
    background: rgba(255,255,255,.34) !important;
    border-color: rgba(255,255,255,.30) !important;
}

.alarm-kpis .alarm-kpi:nth-child(2) .wai-live-spark polyline {
    stroke: rgba(66,32,6,.86) !important;
}

/* Diğer KPI'lar beyaz metin */
.alarm-kpis .alarm-kpi:nth-child(1) strong,
.alarm-kpis .alarm-kpi:nth-child(1) span,
.alarm-kpis .alarm-kpi:nth-child(3) strong,
.alarm-kpis .alarm-kpi:nth-child(3) span,
.alarm-kpis .alarm-kpi:nth-child(4) strong,
.alarm-kpis .alarm-kpi:nth-child(4) span {
    color: #fff !important;
}

/* SLA kartları nötr ve premium */
.alarm-sla-card {
    background: #fff !important;
    border: 1px solid #e2e8e4 !important;
    box-shadow: 0 10px 26px rgba(15,23,42,.045);
}

.alarm-sla-card:nth-child(1) {
    border-top: 4px solid #ef4444 !important;
}

.alarm-sla-card:nth-child(2) {
    border-top: 4px solid #3b82f6 !important;
}

.alarm-sla-card:nth-child(3) {
    border-top: 4px solid #f59e0b !important;
}

.alarm-sla-card strong {
    color: #111a14 !important;
}

.alarm-sla-card span {
    color: #748078 !important;
}

/* Riskli müşteriler ve personel performansı beyaz */
.risk-customers-card,
.alarm-staff-card {
    background: #fff !important;
    box-shadow: 0 10px 26px rgba(15,23,42,.045);
}

.risk-customers-card {
    border: 1px solid #f0cccc !important;
}

.alarm-staff-card {
    border: 1px solid #d8e4f0 !important;
}

.risk-customers-title {
    color: #b91c1c !important;
}

.alarm-staff-title {
    color: #1d4ed8 !important;
}

</style>

<div class="wai-alarm-center"

    wire:poll.60s>

    <section class="alarm-hero">
        <h1>WAI Alarm Merkezi</h1>

        <p>
            Kritik satış fırsatlarını, geciken sıcak leadleri ve kayıp riski taşıyan
            müşterileri tek ekrandan yönetin.
        </p>
    </section>


        @php $live=$this->liveKpiTrends; @endphp

<section class="alarm-kpis">

        <div class="alarm-kpi wai-live-card red">
            <strong>{{ $this->activeCount }}</strong>
            <span>Aktif Alarm</span>
        </div>

        <div class="alarm-kpi wai-live-card orange">
            <strong>{{ $this->criticalCount }}</strong>
            <span>Kritik Alarm</span>
        <div class="wai-live-trend {{ $live['active']['trend_direction'] }}">{{ $live['active']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['active']['points'] }}"/></svg><div class="wai-live-trend {{ $live['critical']['trend_direction'] }}">{{ $live['critical']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['critical']['points'] }}"/></svg></div>
</div>
</div>

        <div class="alarm-kpi wai-live-card green">
            <strong>{{ $this->resolvedTodayCount }}</strong>
            <span>Bugün Çözülen</span>
        </div>

        <div class="alarm-kpi wai-live-card indigo">
            <strong>{{ $this->snoozedCount }}</strong>
            <span>Ertelenen Alarm</span>
        <div class="wai-live-trend {{ $live['resolved']['trend_direction'] }}">{{ $live['resolved']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['resolved']['points'] }}"/></svg><div class="wai-live-trend {{ $live['snoozed']['trend_direction'] }}">{{ $live['snoozed']['trend_label'] }}</div>
<div class="wai-live-spark"><svg viewBox="0 0 108 42"><polyline points="{{ $live['snoozed']['points'] }}"/></svg></div>
</div>
</div>

    </section>


    @php
        $oldestActiveAlarm =
            $this->oldestActiveAlarm;
    @endphp

    <section class="alarm-sla-summary">

        <div class="alarm-sla-card danger">
            <strong>
                {{ $this->slaBreachedCount }}
            </strong>
            <span>SLA Aşımı</span>
        </div>

        <div class="alarm-sla-card">
            <strong>
                {{ $this->averageResolutionLabel }}
            </strong>
            <span>Son 30 Gün Ortalama Çözüm</span>
        </div>

        <div class="alarm-sla-card">
            <strong>
                {{
                    $oldestActiveAlarm
                        ? $this->alarmAgeLabel(
                            $oldestActiveAlarm
                        )
                        : '-'
                }}
            </strong>
            <span>En Eski Aktif Alarm</span>
        </div>

    </section>


    <section class="alarm-toolbar">

        <select
            class="alarm-select"
            wire:model.live="statusFilter"
        >
            <option value="active">Aktif Alarmlar</option>
            <option value="resolved">Çözülenler</option>
            <option value="snoozed">Ertelenenler</option>
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

        <input
            type="search"
            class="alarm-select"
            wire:model.live.debounce.400ms="search"
            placeholder="Müşteri veya alarm ara..."
        >


        <button
            type="button"
            class="alarm-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    <section class="risk-customers-card">

        <div class="risk-customers-head">

            <div class="risk-customers-title">
                Günün En Riskli 5 Müşterisi
            </div>

            <div class="risk-customers-sub">
                Alarm önceliği, lead skoru, fırsat değeri ve gecikme sinyalleri birlikte değerlendirilir.
            </div>

        </div>

        <div class="risk-customer-list">

            @forelse ($this->topRiskCustomers as $riskCustomer)

                <div class="risk-customer-row">

                    <div class="risk-score-box">
                        {{ $riskCustomer['risk_score'] }}
                    </div>

                    <div>

                        <div class="risk-customer-name">
                            {{ $riskCustomer['customer_name'] }}
                        </div>

                        <div class="risk-customer-meta">
                            Lead:
                            {{ $riskCustomer['lead_score'] }}/100

                            · Alarm:
                            {{ $riskCustomer['alarm_priority'] }}/100

                            · {{ $riskCustomer['alarm_type'] }}

                            @if ($riskCustomer['estimated_value'] > 0)
                                · Fırsat:
                                {{ number_format((float) $riskCustomer['estimated_value'], 2, ',', '.') }} ₺
                            @endif

                            @if ($riskCustomer['alarm_count'] > 1)
                                · {{ $riskCustomer['alarm_count'] }} aktif alarm
                            @endif

                            @if ($riskCustomer['assigned_user'])
                                · Sorumlu:
                                {{ $riskCustomer['assigned_user'] }}
                            @endif
                        </div>

                        @if ($riskCustomer['recommended_action'])
                            <div class="risk-customer-action">
                                {{ $riskCustomer['recommended_action'] }}
                            </div>
                        @endif

                    </div>

                    @if ($riskCustomer['conversation_id'] > 0)
                        <a
                            class="risk-customer-open"
                            href="{{
                                url(
                                    '/admin/musteriler?customer='
                                    .$riskCustomer['conversation_id']
                                )
                            }}"
                        >
                            Müşteriyi Aç
                        </a>
                    @endif

                </div>

            @empty

                <div class="alarm-staff-empty">
                    Şu anda risk sıralamasına girecek aktif alarm bulunmuyor.
                </div>

            @endforelse

        </div>

    </section>


    <section class="alarm-staff-card">

        <div class="alarm-staff-head">

            <div>
                <div class="alarm-staff-title">
                    Personel Alarm Performansı
                </div>

                <div class="alarm-staff-sub">
                    Aktif alarm, kritik alarm, SLA ihlali ve son 30 günlük çözüm performansı.
                </div>
            </div>

        </div>

        <div class="alarm-staff-scroll">

            <table class="alarm-staff-table">

                <thead>
                    <tr>
                        <th>Personel</th>
                        <th>Aktif Alarm</th>
                        <th>Kritik</th>
                        <th>SLA Aşımı</th>
                        <th>30 Günde Çözülen</th>
                        <th>Ort. Çözüm</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($this->staffAlarmPerformance as $staff)

                        <tr>

                            <td class="alarm-staff-name">
                                {{ $staff['name'] }}
                            </td>

                            <td>
                                {{ $staff['active'] }}
                            </td>

                            <td
                                class="{{
                                    $staff['critical'] > 0
                                        ? 'alarm-staff-danger'
                                        : ''
                                }}"
                            >
                                {{ $staff['critical'] }}
                            </td>

                            <td
                                class="{{
                                    $staff['sla_breached'] > 0
                                        ? 'alarm-staff-danger'
                                        : 'alarm-staff-good'
                                }}"
                            >
                                {{ $staff['sla_breached'] }}
                            </td>

                            <td>
                                {{ $staff['resolved_30_days'] }}
                            </td>

                            <td>
                                {{ $staff['average_resolution_label'] }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="alarm-staff-empty"
                            >
                                Henüz sorumlu personel atanmış alarm verisi bulunmuyor.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

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

                        @php
                            $priorityClass =
                                match (true) {
                                    $alarm->priority_score >= 95 =>
                                        'urgent',

                                    $alarm->priority_score >= 85 =>
                                        'very-high',

                                    $alarm->priority_score >= 70 =>
                                        'high',

                                    default =>
                                        '',
                                };
                        @endphp

                        <span
                            class="
                                alarm-priority
                                {{ $priorityClass }}
                            "
                        >
                            Öncelik:
                            {{ $alarm->priority_score }}/100
                            ·
                            {{ $alarm->priorityLabel() }}
                        </span>

                        <span
                            class="
                                alarm-sla
                                {{
                                    $this->alarmSlaBreached(
                                        $alarm
                                    )
                                        ? 'breached'
                                        : ''
                                }}
                            "
                        >
                            @if (
                                $this->alarmSlaBreached(
                                    $alarm
                                )
                            )
                                SLA Aşıldı
                            @else
                                Açık:
                                {{ $this->alarmAgeLabel($alarm) }}
                            @endif
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

                    @if ($alarm->recommended_action)

                        <div class="alarm-action-box">

                            <div class="alarm-action-title">
                                Önerilen Aksiyon
                            </div>

                            <div class="alarm-action-text">
                                {{ $alarm->recommended_action }}
                            </div>

                        </div>

                    @endif

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


                <div>

                    @if ($alarm->isSnoozed())

                        <div class="alarm-snoozed-info">
                            Ertelendi:
                            {{ $alarm->snoozed_until->format('d.m.Y H:i') }}
                        </div>

                    @elseif (! $alarm->is_resolved)

                        <div class="alarm-snooze-box">

                            <div class="alarm-snooze-title">
                                Alarmı Ertele
                            </div>

                            <div class="alarm-snooze-buttons">

                                <button
                                    type="button"
                                    class="alarm-snooze-button"
                                    wire:click="snoozeAlarm({{ $alarm->id }}, '1h')"
                                >
                                    1 Saat
                                </button>

                                <button
                                    type="button"
                                    class="alarm-snooze-button"
                                    wire:click="snoozeAlarm({{ $alarm->id }}, '3h')"
                                >
                                    3 Saat
                                </button>

                                <button
                                    type="button"
                                    class="alarm-snooze-button"
                                    wire:click="snoozeAlarm({{ $alarm->id }}, 'tomorrow')"
                                >
                                    Yarın
                                </button>

                            </div>

                            <div class="alarm-custom-snooze">

                                <input
                                    type="datetime-local"
                                    class="alarm-custom-input"
                                    wire:model="customSnoozeUntil.{{ $alarm->id }}"
                                >

                                <button
                                    type="button"
                                    class="alarm-snooze-button"
                                    wire:click="snoozeAlarmCustom({{ $alarm->id }})"
                                >
                                    Uygula
                                </button>

                            </div>

                        </div>

                    @endif

                </div>


                <div>

                    <button
                        type="button"
                        class="alarm-history-toggle"
                        wire:click="toggleAlarmHistory({{ $alarm->id }})"
                    >
                        {{
                            in_array(
                                $alarm->id,
                                $expandedHistories,
                                true
                            )
                                ? 'Geçmişi Gizle'
                                : 'Alarm Geçmişi'
                        }}
                    </button>

                    @if (
                        in_array(
                            $alarm->id,
                            $expandedHistories,
                            true
                        )
                    )

                        <div class="alarm-history">

                            @forelse ($alarm->events as $event)

                                <div class="alarm-history-item">

                                    <div class="alarm-history-title">
                                        {{ $event->eventLabel() }}
                                    </div>

                                    <div class="alarm-history-meta">
                                        {{ $event->created_at->format('d.m.Y H:i') }}

                                        @if ($event->user)
                                            · {{ $event->user->name }}
                                        @else
                                            · Sistem
                                        @endif
                                    </div>

                                    @if ($event->description)
                                        <div class="alarm-history-description">
                                            {{ $event->description }}
                                        </div>
                                    @endif

                                </div>

                            @empty

                                <div class="alarm-history-description">
                                    Bu alarm için henüz geçmiş kaydı bulunmuyor.
                                </div>

                            @endforelse

                        </div>

                    @endif

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

                        @if ($alarm->isSnoozed())
                            <button
                                type="button"
                                class="alarm-button"
                                wire:click="clearAlarmSnooze({{ $alarm->id }})"
                            >
                                Ertelemeyi Kaldır
                            </button>
                        @endif

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