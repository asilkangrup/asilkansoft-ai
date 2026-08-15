<x-filament-panels::page>

<style>

[x-cloak] {
    display: none !important;
}

.wai-crm {
    --green: #24e17f;
    --green-dark: #087a42;
    --green-deep: #075d35;
    --green-soft: #effcf5;
    --ink: #101712;
    --text: #3f4b43;
    --muted: #77827b;
    --line: #e5ebe7;
    --soft: #f7f9f8;
    --white: #ffffff;
    width: 100%;
}

/* ==========================================================================
   HERO
   ========================================================================== */

.crm-hero {
    position: relative;
    margin-bottom: 18px;
    padding: 28px 30px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    border: 1px solid var(--line);
    border-radius: 24px;
    background:
        radial-gradient(circle at 90% 20%, rgba(36,225,127,.12), transparent 28%),
        linear-gradient(145deg, #ffffff, #fbfdfc);
    box-shadow: 0 18px 55px rgba(10,30,17,.05);
}

.crm-hero::after {
    content: "";
    position: absolute;
    width: 280px;
    height: 280px;
    right: -130px;
    top: -140px;
    border-radius: 50%;
    border: 1px solid rgba(36,225,127,.13);
    box-shadow:
        0 0 0 38px rgba(36,225,127,.025),
        0 0 0 76px rgba(36,225,127,.012);
    pointer-events: none;
}

.crm-hero-copy {
    position: relative;
    z-index: 2;
}

.crm-eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--green-deep);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.crm-eyebrow-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 5px rgba(36,225,127,.10);
}

.crm-hero h1 {
    margin: 8px 0 0;
    color: var(--ink);
    font-size: 30px;
    font-weight: 850;
    letter-spacing: -1px;
}

.crm-hero p {
    max-width: 650px;
    margin: 8px 0 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.65;
}

.crm-hero-live {
    position: relative;
    z-index: 2;
    min-height: 44px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #ccefd9;
    border-radius: 13px;
    color: var(--green-deep);
    background: var(--green-soft);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.crm-hero-live i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
}

/* ==========================================================================
   KPI
   ========================================================================== */

.crm-kpis {
    margin-bottom: 18px;
    display: grid;
    grid-template-columns: repeat(7, minmax(0,1fr));
    gap: 12px;
}

.crm-kpi {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(10,30,17,.035);
}

.crm-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.crm-kpi-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    color: var(--green-dark);
    background: var(--green-soft);
}

.crm-kpi-icon svg {
    width: 20px;
    height: 20px;
}

.crm-kpi-number {
    margin-top: 17px;
    color: var(--ink);
    font-size: 29px;
    font-weight: 900;
    letter-spacing: -1px;
}

.crm-kpi-label {
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

/* ==========================================================================
   REVENUE SUMMARY
   ========================================================================== */

.crm-revenue {
    margin-bottom: 18px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 10px;
}

.crm-revenue-card {
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 8px 25px rgba(10,30,17,.03);
}

.crm-revenue-value {
    color: var(--ink);
    font-size: 23px;
    font-weight: 900;
    letter-spacing: -.6px;
}

.crm-revenue-label {
    margin-top: 5px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
}

.crm-revenue-diff.positive {
    color: #087a42;
}

.crm-revenue-diff.negative {
    color: #a14646;
}

@media (max-width: 1250px) {
    .crm-revenue {
        grid-template-columns: repeat(2,1fr);
    }
}

@media (max-width: 800px) {
    .crm-revenue {
        grid-template-columns: 1fr;
    }
}

/* ==========================================================================
   SALES FORECAST
   ========================================================================== */

.crm-forecast {
    margin-bottom: 18px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
}

.crm-forecast-card {
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36,225,127,.08), transparent 34%),
        #fff;
    box-shadow: 0 8px 25px rgba(10,30,17,.03);
}

.crm-forecast-value {
    color: var(--ink);
    font-size: 24px;
    font-weight: 900;
    letter-spacing: -.7px;
}

.crm-forecast-label {
    margin-top: 5px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
}

.crm-probability {
    margin-top: 10px;
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: #e9efeb;
}

.crm-probability span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6defaa, #21d878);
}

@media (max-width: 800px) {
    .crm-forecast {
        grid-template-columns: 1fr;
    }
}

/* ==========================================================================
   DAILY SALES CENTER
   ========================================================================== */

.crm-daily-center {
    margin-bottom: 18px;
}

.crm-daily-head {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.crm-daily-title {
    color: var(--ink);
    font-size: 15px;
    font-weight: 900;
}

.crm-daily-subtitle {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.crm-daily-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 10px;
}

.crm-daily-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(10,30,17,.025);
}

.crm-daily-card-head {
    min-height: 52px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-bottom: 1px solid #edf1ee;
}

.crm-daily-card-title {
    color: #263229;
    font-size: 11px;
    font-weight: 900;
}

.crm-daily-count {
    min-width: 26px;
    height: 26px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #087a42;
    background: #effcf5;
    font-size: 9px;
    font-weight: 900;
}

.crm-daily-list {
    max-height: 250px;
    overflow-y: auto;
}

.crm-daily-item {
    padding: 11px 13px;
    border-bottom: 1px solid #f0f3f1;
    cursor: pointer;
    transition: background .16s ease;
}

.crm-daily-item:last-child {
    border-bottom: 0;
}

.crm-daily-item:hover {
    background: #fafcfb;
}

.crm-daily-item-name {
    overflow: hidden;
    color: #263229;
    font-size: 10px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.crm-daily-item-meta {
    margin-top: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #8a958e;
    font-size: 9px;
    font-weight: 700;
}

.crm-daily-empty {
    padding: 18px 12px;
    color: #8b958f;
    text-align: center;
    font-size: 10px;
    line-height: 1.5;
}

@media (max-width: 1250px) {
    .crm-daily-grid {
        grid-template-columns: repeat(2,1fr);
    }
}

@media (max-width: 800px) {
    .crm-daily-grid {
        grid-template-columns: 1fr;
    }
}

/* ==========================================================================
   FILTER BAR
   ========================================================================== */

.crm-toolbar {
    margin-bottom: 14px;
    padding: 13px;
    display: grid;
    grid-template-columns:
        minmax(250px,1fr)
        160px
        150px
        140px
        170px
        auto;
    gap: 9px;
    border: 1px solid var(--line);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 8px 26px rgba(10,30,17,.025);
}

.crm-input-wrap {
    position: relative;
}

.crm-input-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    width: 18px;
    height: 18px;
    color: #89958d;
    transform: translateY(-50%);
}

.crm-search {
    width: 100%;
    height: 44px;
    padding: 0 13px 0 43px;
    outline: none;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    color: #28342c;
    background: #fafcfb;
    font-size: 13px;
}

.crm-search:focus,
.crm-select:focus {
    border-color: #91dfb1;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(36,225,127,.06);
}

.crm-select {
    width: 100%;
    height: 44px;
    padding: 0 12px;
    outline: none;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    color: #3b473f;
    background: #fafcfb;
    font-size: 12px;
    font-weight: 700;
}

.crm-reset {
    height: 44px;
    padding: 0 15px;
    border: 1px solid #dde5e0;
    border-radius: 12px;
    color: #657168;
    background: #fff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

/* ==========================================================================
   MAIN SHELL
   ========================================================================== */

.crm-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 390px;
    min-height: 720px;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 18px 55px rgba(10,30,17,.05);
}

/* ==========================================================================
   CUSTOMER LIST
   ========================================================================== */

.crm-list-area {
    min-width: 0;
    overflow: hidden;
    background: #fff;
}

.crm-table-head {
    min-height: 64px;
    padding: 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--line);
}

.crm-table-title {
    color: var(--ink);
    font-size: 16px;
    font-weight: 850;
}

.crm-result-count {
    min-height: 29px;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    color: var(--green-deep);
    background: var(--green-soft);
    font-size: 10px;
    font-weight: 900;
}

.crm-list {
    height: 720px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #dce4df transparent;
}

.customer-row {
    position: relative;
    min-height: 82px;
    padding: 15px 18px;
    display: grid;
    grid-template-columns:
        minmax(220px,1.45fr)
        minmax(130px,.8fr)
        125px
        100px
        85px;
    align-items: center;
    gap: 14px;
    border-bottom: 1px solid #edf1ee;
    cursor: pointer;
    transition: background .18s ease;
}

.customer-row:hover {
    background: #fafcfb;
}

.customer-row.active {
    background: linear-gradient(90deg, #effcf5, #fbfefc);
}

.customer-row.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: var(--green);
}

.customer-main {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.customer-avatar {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    flex: 0 0 48px;
    border: 1px solid #ccefd9;
    border-radius: 15px;
    color: var(--green-deep);
    background: linear-gradient(145deg, #effcf5, #e7faef);
    font-size: 15px;
    font-weight: 900;
}

.customer-copy {
    min-width: 0;
}

.customer-name {
    overflow: hidden;
    color: #202b24;
    font-size: 13px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-phone {
    margin-top: 5px;
    overflow: hidden;
    color: #818c85;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.crm-pill {
    width: fit-content;
    min-height: 29px;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 850;
}

.crm-pill.status-new {
    color: #526159;
    background: #f0f4f1;
}

.crm-pill.status-contacted {
    color: #2e5a87;
    background: #edf5fc;
}

.crm-pill.status-qualified {
    color: #7054aa;
    background: #f4effd;
}

.crm-pill.status-proposal {
    color: #996200;
    background: #fff5db;
}

.crm-pill.status-won {
    color: #08733d;
    background: #eafaf1;
}

.crm-pill.status-lost {
    color: #a04444;
    background: #fdf0f0;
}

.temp-hot {
    color: #b1422f;
    background: #fff0ec;
}

.temp-warm {
    color: #a06600;
    background: #fff6de;
}

.temp-cold {
    color: #506b80;
    background: #eff5f8;
}

.channel-pill {
    color: #526159;
    background: #f2f6f3;
}

.score-wrap {
    min-width: 0;
}

.score-number {
    color: #27332b;
    font-size: 12px;
    font-weight: 900;
}

.score-bar {
    height: 5px;
    margin-top: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: #ebefec;
}

.score-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #67efaa, #20d976);
}

.crm-date {
    color: #87928b;
    font-size: 10px;
    font-weight: 700;
}

.crm-empty {
    padding: 65px 25px;
    text-align: center;
    color: #87928b;
    font-size: 13px;
}

/* ==========================================================================
   DETAIL PANEL
   ========================================================================== */

.crm-detail {
    min-width: 0;
    height: 784px;
    overflow-y: auto;
    border-left: 1px solid var(--line);
    background: linear-gradient(180deg, #ffffff, #fbfcfb);
    scrollbar-width: thin;
    scrollbar-color: #dce4df transparent;
}

.crm-detail-head {
    min-height: 190px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border-bottom: 1px solid var(--line);
    background:
        radial-gradient(
            circle at 50% 0%,
            rgba(36,225,127,.10),
            transparent 55%
        );
}

.crm-detail-avatar {
    width: 70px;
    height: 70px;
    margin: 0 auto 11px;
    display: grid;
    place-items: center;
    border: 1px solid #c9ecd6;
    border-radius: 21px;
    color: var(--green-deep);
    background: var(--green-soft);
    font-size: 23px;
    font-weight: 900;
}

.crm-detail-name {
    color: var(--ink);
    font-size: 17px;
    font-weight: 850;
}

.crm-detail-phone {
    margin-top: 4px;
    color: #7b877f;
    font-size: 11px;
}

.crm-detail-badges {
    margin-top: 11px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 6px;
}

/* ==========================================================================
   CRM TAGS
   ========================================================================== */

.crm-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.crm-tag {
    min-height: 27px;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    border: 1px solid #dde7e1;
    border-radius: 999px;
    color: #657168;
    background: #f8faf9;
    font-size: 9px;
    font-weight: 850;
}

.crm-tag.priority {
    border-color: #ffdca6;
    color: #9a5e00;
    background: #fff7e8;
}

.crm-tag.risk {
    border-color: #f0c8c8;
    color: #a14646;
    background: #fff3f3;
}

.crm-tag.intent {
    border-color: #c8e9d5;
    color: #087a42;
    background: #effcf5;
}

/* ==========================================================================
   DETAIL SECTIONS
   ========================================================================== */

.crm-form-section {
    padding: 20px 22px;
    border-bottom: 1px solid var(--line);
    background: #fff;
}

.crm-form-section.crm-section-standard {
    min-height: 145px;
}

.crm-form-title {
    min-height: 18px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    color: #66736b;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.crm-field {
    margin-bottom: 13px;
}

.crm-field:last-child {
    margin-bottom: 0;
}

.crm-field label {
    display: block;
    min-height: 17px;
    margin-bottom: 7px;
    color: #536058;
    font-size: 11px;
    font-weight: 800;
}

.crm-field input,
.crm-field select,
.crm-field textarea {
    width: 100%;
    outline: 0;
    border: 1px solid #dce5df;
    border-radius: 12px;
    color: #2e3932;
    background: #fff;
    font-family: inherit;
    font-size: 12px;
    transition:
        border-color .18s ease,
        box-shadow .18s ease;
}

.crm-field input,
.crm-field select {
    height: 52px;
    padding: 0 14px;
}

.crm-field textarea {
    min-height: 124px;
    padding: 14px;
    resize: vertical;
    line-height: 1.55;
}

.crm-field input:focus,
.crm-field select:focus,
.crm-field textarea:focus {
    border-color: #91dfb1;
    box-shadow: 0 0 0 4px rgba(36,225,127,.055);
}

/* ==========================================================================
   WAI AI INSIGHT
   ========================================================================== */

.crm-ai-card {
    padding: 18px;
    border: 1px solid #cfeedd;
    border-radius: 16px;
    background:
        radial-gradient(circle at 100% 0%, rgba(36,225,127,.09), transparent 36%),
        linear-gradient(145deg, #fbfffd, #f3fcf7);
}

.crm-ai-card + .crm-ai-card {
    margin-top: 10px;
}

.crm-ai-card.action {
    border-color: #dce5ff;
    background:
        radial-gradient(circle at 100% 0%, rgba(91,126,220,.08), transparent 36%),
        linear-gradient(145deg, #fcfdff, #f6f8ff);
}

.crm-ai-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.crm-ai-card-title {
    color: #1e4932;
    font-size: 11px;
    font-weight: 900;
}

.crm-ai-card.action .crm-ai-card-title {
    color: #38538c;
}

.crm-ai-card-body {
    margin-top: 9px;
    color: #536159;
    font-size: 11px;
    line-height: 1.65;
    white-space: pre-line;
}

.crm-ai-empty {
    color: #89958d;
    font-style: italic;
}

.crm-ai-meta {
    margin-top: 9px;
    color: #8b978f;
    font-size: 9px;
    font-weight: 700;
}

.crm-ai-refresh {
    min-height: 31px;
    padding: 0 10px;
    border: 1px solid #ccefd9;
    border-radius: 9px;
    color: #087a42;
    background: #fff;
    font-size: 9px;
    font-weight: 900;
    cursor: pointer;
}

.crm-ai-refresh:disabled {
    opacity: .6;
    cursor: wait;
}

/* ==========================================================================
   LEAD SCORE
   ========================================================================== */

.crm-score-row {
    display: grid;
    grid-template-columns: minmax(0,1fr) 88px;
    align-items: end;
    gap: 12px;
}

.crm-score-row .crm-field {
    margin-bottom: 0;
}

.crm-score-range {
    width: 100%;
    accent-color: #20d976;
}

/* ==========================================================================
   STATUS BUTTONS
   ========================================================================== */

.crm-quick-status {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 7px;
}

.crm-quick-status button {
    min-height: 42px;
    padding: 6px 8px;
    border: 1px solid var(--line);
    border-radius: 11px;
    color: #66736b;
    background: #fff;
    font-size: 9px;
    font-weight: 800;
    cursor: pointer;
    transition:
        border-color .18s ease,
        background .18s ease,
        color .18s ease;
}

.crm-quick-status button:hover {
    border-color: #ccefd9;
    color: var(--green-deep);
    background: #fbfefc;
}

.crm-quick-status button.active {
    border-color: #bfe9cf;
    color: var(--green-deep);
    background: var(--green-soft);
}

/* ==========================================================================
   SAVE + SECONDARY ACTIONS
   ========================================================================== */

.crm-save {
    width: 100%;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 12px;
    color: #07331b;
    background: linear-gradient(135deg, #6bf1a9, #2dde81);
    box-shadow: 0 10px 25px rgba(36,225,127,.17);
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.crm-save svg {
    width: 17px;
    height: 17px;
}

.crm-secondary-actions {
    margin-top: 9px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.crm-secondary-actions button,
.crm-secondary-actions a {
    min-height: 50px;
    padding: 0 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    border-radius: 11px;
    color: #5c6961;
    background: #fff;
    text-decoration: none;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
}

/* ==========================================================================
   TIMELINE FILTERS
   ========================================================================== */

.crm-timeline-filters {
    margin-bottom: 15px;

    display: flex;
    flex-wrap: wrap;

    gap: 7px;
}

.crm-timeline-filter {
    min-height: 32px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border: 1px solid #dfe7e2;
    border-radius: 999px;

    color: #6d7971;
    background: #fff;

    font-size: 9px;
    font-weight: 850;

    cursor: pointer;

    transition:
        border-color .18s ease,
        color .18s ease,
        background .18s ease;
}

.crm-timeline-filter:hover {
    border-color: #c7ead5;

    color: #087a42;

    background: #f8fdf9;
}

.crm-timeline-filter.active {
    border-color: #bfe9cf;

    color: #087a42;

    background: #effcf5;
}

/* ==========================================================================
   CRM TIMELINE
   ========================================================================== */

.crm-timeline {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.crm-timeline-item {
    position: relative;
    padding: 0 0 18px 30px;
}

.crm-timeline-item:last-child {
    padding-bottom: 0;
}

.crm-timeline-item::before {
    content: "";
    position: absolute;
    left: 8px;
    top: 18px;
    bottom: -2px;
    width: 1px;
    background: #e3ebe6;
}

.crm-timeline-item:last-child::before {
    display: none;
}

.crm-timeline-dot {
    position: absolute;
    left: 2px;
    top: 5px;
    width: 13px;
    height: 13px;
    border: 3px solid #fff;
    border-radius: 50%;
    background: #9ba79f;
    box-shadow: 0 0 0 1px #dce5df;
}

.crm-timeline-dot.ai_score,
.crm-timeline-dot.ai_status,
.crm-timeline-dot.ai_action {
    background: #24e17f;
}

.crm-timeline-dot.lead_status,
.crm-timeline-dot.lead_score,
.crm-timeline-dot.lead_temperature {
    background: #5d8ed8;
}

.crm-timeline-dot.assignment {
    background: #8b68c7;
}

.crm-timeline-dot.follow_up {
    background: #d79a25;
}

.crm-timeline-dot.note {
    background: #6d7c73;
}

.crm-timeline-dot.human_takeover {
    background: #c35d5d;
}

.crm-timeline-dot.ai_release {
    background: #24b87a;
}

.crm-timeline-dot.won {
    background: #24b86c;
}

.crm-timeline-dot.lost {
    background: #d05b5b;
}

.crm-timeline-content {
    min-width: 0;
}

.crm-timeline-title {
    color: #263229;
    font-size: 11px;
    font-weight: 850;
    line-height: 1.4;
}

.crm-timeline-description {
    margin-top: 4px;
    color: #758078;
    font-size: 10px;
    line-height: 1.5;
    word-break: break-word;
}

.crm-timeline-meta {
    margin-top: 6px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: #9aa39d;
    font-size: 9px;
    font-weight: 700;
}

.crm-timeline-actor {
    color: #607168;
}

.crm-timeline-empty {
    padding: 18px 14px;
    border: 1px dashed #dce5df;
    border-radius: 12px;
    color: #8a958e;
    background: #fafcfb;
    text-align: center;
    font-size: 10px;
    line-height: 1.5;
}

/* ==========================================================================
   TOAST
   ========================================================================== */

.crm-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 99999;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid #c9ecd6;
    border-radius: 13px;
    color: var(--green-deep);
    background: #f1fcf6;
    box-shadow: 0 18px 45px rgba(7,40,21,.13);
    font-size: 12px;
    font-weight: 850;
}

.crm-toast svg {
    width: 18px;
    height: 18px;
}

/* ==========================================================================
   TABLET
   ========================================================================== */

@media (max-width: 1250px) {

    .crm-kpis {
        grid-template-columns: repeat(3,1fr);
    }

    .crm-toolbar {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .crm-input-wrap {
        grid-column: 1 / -1;
    }

    .crm-shell {
        grid-template-columns: minmax(0,1fr) 350px;
    }

    .customer-row {
        grid-template-columns:
            minmax(190px,1.4fr)
            115px
            95px
            75px;
    }

    .customer-row > :nth-child(5) {
        display: none;
    }
}

/* ==========================================================================
   MOBILE
   ========================================================================== */

@media (max-width: 800px) {

    .crm-hero {
        padding: 20px;
        display: block;
        border-radius: 19px;
    }

    .crm-hero h1 {
        font-size: 25px;
    }

    .crm-hero p {
        font-size: 13px;
    }

    .crm-hero-live {
        width: 100%;
        margin-top: 14px;
        justify-content: center;
    }

    .crm-kpis {
        grid-template-columns: repeat(2,1fr);
        gap: 9px;
    }

    .crm-kpi {
        padding: 15px;
    }

    .crm-kpi-number {
        font-size: 26px;
    }

    .crm-kpi-label {
        font-size: 11px;
    }

    .crm-toolbar {
        grid-template-columns: 1fr 1fr;
        padding: 10px;
    }

    .crm-input-wrap {
        grid-column: 1 / -1;
    }

    .crm-reset {
        grid-column: 1 / -1;
    }

    .crm-shell {
        display: block;
        min-height: 0;
        overflow: visible;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
    }

    .crm-list-area {
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 19px;
        background: #fff;
    }

    .crm-list {
        height: auto;
        max-height: none;
    }

    .customer-row {
        min-height: 76px;
        padding: 14px;
        grid-template-columns: minmax(0,1fr) auto;
        gap: 10px;
    }

    .customer-row > :nth-child(3),
    .customer-row > :nth-child(4),
    .customer-row > :nth-child(5) {
        display: none;
    }

    .customer-avatar {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
    }

    .customer-name {
        font-size: 13px;
    }

    .crm-detail {
        height: auto;
        margin-top: 14px;
        overflow: visible;
        border: 1px solid var(--line);
        border-radius: 19px;
        background: #fff;
    }

    .crm-detail-head {
        min-height: 175px;
    }

    .crm-detail-name {
        font-size: 17px;
    }

    .crm-form-section {
        padding: 18px;
    }

    .crm-form-section.crm-section-standard {
        min-height: auto;
    }

    .crm-field input,
    .crm-field select,
    .crm-field textarea {
        font-size: 16px;
    }

    .crm-quick-status {
        grid-template-columns: repeat(2,1fr);
    }

    .crm-score-row {
        grid-template-columns: 1fr;
    }

    .crm-toast {
        left: 12px;
        right: 12px;
        bottom: 12px;
        justify-content: center;
    }
}

</style>


<div
    class="wai-crm"

    x-data="{
        saved: false,
        summaryRefreshed: false,
        summaryFailed: false
    }"

    x-on:crm-customer-saved.window="
        saved = true;
        setTimeout(
            () => saved = false,
            2200
        );
    "
    x-on:crm-ai-summary-refreshed.window="
        summaryRefreshed = true;
        setTimeout(
            () => summaryRefreshed = false,
            2200
        );
    "
    x-on:crm-ai-summary-failed.window="
        summaryFailed = true;
        setTimeout(
            () => summaryFailed = false,
            3000
        );
    "
>

    {{-- =========================================================
         HERO
    ========================================================== --}}

    <section class="crm-hero">

        <div class="crm-hero-copy">

            <div class="crm-eyebrow">

                <span class="crm-eyebrow-dot"></span>

                WAI CRM

            </div>

            <h1>
                Müşterilerinizi satışa dönüştürün.
            </h1>

            <p>
                Tüm müşteri görüşmelerinizi, lead durumlarını,
                satış fırsatlarını ve takip süreçlerini tek merkezden yönetin.
            </p>

        </div>

        <div class="crm-hero-live">

            <i></i>

            Canlı CRM Merkezi

        </div>

    </section>


    {{-- =========================================================
         KPI
    ========================================================== --}}

    <section class="crm-kpis">

        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="9" cy="8" r="3"/>
                        <circle cx="17" cy="10" r="2"/>
                        <path d="M3 20c.6-4 2.8-6 6-6s5.4 2 6 6"/>
                        <path d="M15 15c3 0 5 1.5 6 4"/>
                    </svg>

                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->totalCustomers }}
            </div>

            <div class="crm-kpi-label">
                Toplam Müşteri
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M13 2 5 13h6l-1 9 9-13h-6V2Z"/>
                    </svg>

                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->hotCustomers }}
            </div>

            <div class="crm-kpi-label">
                Sıcak Lead
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">
                    🔥
                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->priorityCustomers }}
            </div>

            <div class="crm-kpi-label">
                Öncelikli Lead
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">
                    ⚠️
                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->riskCustomers }}
            </div>

            <div class="crm-kpi-label">
                Riskli Lead
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M5 4h14v16H5z"/>
                        <path d="M8 8h8M8 12h8M8 16h5"/>
                    </svg>

                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->proposalCustomers }}
            </div>

            <div class="crm-kpi-label">
                Teklif Aşamasında
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="m5 13 4 4L19 7"/>
                        <circle cx="12" cy="12" r="9"/>
                    </svg>

                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->wonCustomers }}
            </div>

            <div class="crm-kpi-label">
                Kazanılan Satış
            </div>

        </div>


        <div class="crm-kpi">

            <div class="crm-kpi-top">

                <div class="crm-kpi-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="12" r="8"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>

                </div>

            </div>

            <div class="crm-kpi-number">
                {{ $this->followUpCustomers }}
            </div>

            <div class="crm-kpi-label">
                Takip Bekleyen
            </div>

        </div>

    </section>


    {{-- =========================================================
         GERÇEK CİRO
    ========================================================== --}}

    @php
        $comparison =
            $this->forecastComparison;

        $difference =
            (float) ($comparison['difference'] ?? 0);

        $accuracy =
            $comparison['accuracy'] ?? null;
    @endphp

    <section class="crm-revenue">

        <div class="crm-revenue-card">

            <div class="crm-revenue-value">
                {{ number_format($this->totalWonRevenue, 2, ',', '.') }} ₺
            </div>

            <div class="crm-revenue-label">
                Gerçekleşen Toplam Ciro
            </div>

        </div>


        <div class="crm-revenue-card">

            <div class="crm-revenue-value">
                {{ number_format($this->averageWonValue, 2, ',', '.') }} ₺
            </div>

            <div class="crm-revenue-label">
                Ortalama Kazanılan Satış
            </div>

        </div>


        <div class="crm-revenue-card">

            <div
                class="
                    crm-revenue-value
                    crm-revenue-diff
                    {{ $difference >= 0 ? 'positive' : 'negative' }}
                "
            >
                {{ $difference >= 0 ? '+' : '' }}
                {{ number_format($difference, 2, ',', '.') }} ₺
            </div>

            <div class="crm-revenue-label">
                Tahmin / Gerçekleşen Farkı
            </div>

        </div>


        <div class="crm-revenue-card">

            <div class="crm-revenue-value">
                {{ $accuracy !== null ? '%' . $accuracy : '-' }}
            </div>

            <div class="crm-revenue-label">
                Tahmin Doğruluğu
            </div>

        </div>

    </section>


    {{-- =========================================================
         SATIŞ TAHMİNİ
    ========================================================== --}}

    <section class="crm-forecast">

        <div class="crm-forecast-card">

            <div class="crm-forecast-value">
                {{ number_format($this->totalPipelineValue, 2, ',', '.') }} ₺
            </div>

            <div class="crm-forecast-label">
                Toplam Açık Pipeline Değeri
            </div>

        </div>


        <div class="crm-forecast-card">

            <div class="crm-forecast-value">
                {{ number_format($this->weightedPipelineValue, 2, ',', '.') }} ₺
            </div>

            <div class="crm-forecast-label">
                Olasılık Ağırlıklı Pipeline
            </div>

        </div>


        <div class="crm-forecast-card">

            <div class="crm-forecast-value">
                {{ $this->valuedOpportunitiesCount }}
            </div>

            <div class="crm-forecast-label">
                Tutar Girilmiş Açık Fırsat
            </div>

        </div>

    </section>


    {{-- =========================================================
         GÜNLÜK SATIŞ MERKEZİ
    ========================================================== --}}

    <section class="crm-daily-center">

        <div class="crm-daily-head">

            <div>
                <div class="crm-daily-title">
                    Günlük Satış Merkezi
                </div>

                <div class="crm-daily-subtitle">
                    Bugün öncelik vermeniz gereken müşteriler.
                </div>
            </div>

        </div>

        <div class="crm-daily-grid">

            <div class="crm-daily-card">

                <div class="crm-daily-card-head">
                    <div class="crm-daily-card-title">
                        📞 Bugün Aranacaklar
                    </div>

                    <div class="crm-daily-count">
                        {{ $this->todayFollowUps->count() }}
                    </div>
                </div>

                <div class="crm-daily-list">
                    @forelse ($this->todayFollowUps as $customer)

                        <div
                            class="crm-daily-item"
                            wire:click="selectCustomer({{ $customer->id }})"
                        >
                            <div class="crm-daily-item-name">
                                {{ $customer->customer_name ?: $customer->whatsapp_number }}
                            </div>

                            <div class="crm-daily-item-meta">
                                <span>
                                    {{ $customer->lead_score }}/100
                                </span>

                                <span>
                                    {{ $customer->next_follow_up_at?->format('H:i') }}
                                </span>
                            </div>
                        </div>

                    @empty
                        <div class="crm-daily-empty">
                            Bugün için planlanmış takip yok.
                        </div>
                    @endforelse
                </div>

            </div>


            <div class="crm-daily-card">

                <div class="crm-daily-card-head">
                    <div class="crm-daily-card-title">
                        ⏳ 24 Saattir İlgilenilmeyenler
                    </div>

                    <div class="crm-daily-count">
                        {{ $this->unattended24h->count() }}
                    </div>
                </div>

                <div class="crm-daily-list">
                    @forelse ($this->unattended24h as $customer)

                        <div
                            class="crm-daily-item"
                            wire:click="selectCustomer({{ $customer->id }})"
                        >
                            <div class="crm-daily-item-name">
                                {{ $customer->customer_name ?: $customer->whatsapp_number }}
                            </div>

                            <div class="crm-daily-item-meta">
                                <span>
                                    {{ $customer->lead_score }}/100
                                </span>

                                <span>
                                    {{ $customer->last_contact_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                    @empty
                        <div class="crm-daily-empty">
                            24 saattir ilgilenilmeyen müşteri yok.
                        </div>
                    @endforelse
                </div>

            </div>


            <div class="crm-daily-card">

                <div class="crm-daily-card-head">
                    <div class="crm-daily-card-title">
                        💤 7 Gündür Sessiz
                    </div>

                    <div class="crm-daily-count">
                        {{ $this->silent7d->count() }}
                    </div>
                </div>

                <div class="crm-daily-list">
                    @forelse ($this->silent7d as $customer)

                        <div
                            class="crm-daily-item"
                            wire:click="selectCustomer({{ $customer->id }})"
                        >
                            <div class="crm-daily-item-name">
                                {{ $customer->customer_name ?: $customer->whatsapp_number }}
                            </div>

                            <div class="crm-daily-item-meta">
                                <span>
                                    {{ $customer->lead_score }}/100
                                </span>

                                <span>
                                    {{ $customer->last_contact_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                    @empty
                        <div class="crm-daily-empty">
                            7 gündür sessiz kalan açık lead yok.
                        </div>
                    @endforelse
                </div>

            </div>


            <div class="crm-daily-card">

                <div class="crm-daily-card-head">
                    <div class="crm-daily-card-title">
                        🔥 Satışa En Yakın 10
                    </div>

                    <div class="crm-daily-count">
                        {{ $this->closestToSale->count() }}
                    </div>
                </div>

                <div class="crm-daily-list">
                    @forelse ($this->closestToSale as $customer)

                        <div
                            class="crm-daily-item"
                            wire:click="selectCustomer({{ $customer->id }})"
                        >
                            <div class="crm-daily-item-name">
                                {{ $customer->customer_name ?: $customer->whatsapp_number }}
                            </div>

                            <div class="crm-daily-item-meta">
                                <span>
                                    {{ $this->statusLabel($customer->lead_status) }}
                                </span>

                                <span>
                                    {{ $customer->lead_score }}/100
                                </span>
                            </div>
                        </div>

                    @empty
                        <div class="crm-daily-empty">
                            Henüz satış fırsatı oluşmadı.
                        </div>
                    @endforelse
                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         FILTERS
    ========================================================== --}}

    <section class="crm-toolbar">

        <div class="crm-input-wrap">

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
                class="crm-search"
                wire:model.live.debounce.350ms="search"
                placeholder="İsim, telefon, firma veya e-posta ara..."
            >

        </div>


        <select
            class="crm-select"
            wire:model.live="statusFilter"
        >
            <option value="all">Tüm Durumlar</option>
            <option value="new">Yeni Lead</option>
            <option value="contacted">Görüşülüyor</option>
            <option value="qualified">Nitelikli</option>
            <option value="proposal">Teklif</option>
            <option value="won">Kazanıldı</option>
            <option value="lost">Kaybedildi</option>
        </select>


        <select
            class="crm-select"
            wire:model.live="temperatureFilter"
        >
            <option value="all">Tüm Sıcaklıklar</option>
            <option value="hot">Sıcak</option>
            <option value="warm">Ilık</option>
            <option value="cold">Soğuk</option>
        </select>


        <select
            class="crm-select"
            wire:model.live="channelFilter"
        >
            <option value="all">Tüm Kanallar</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="instagram">Instagram</option>
            <option value="facebook">Facebook</option>
            <option value="web">Web</option>
        </select>


        <select
            class="crm-select"
            wire:model.live="opportunityFilter"
        >
            <option value="all">Tüm Fırsatlar</option>
            <option value="priority">🔥 Öncelikli Lead</option>
            <option value="risk">⚠️ Riskli Lead</option>
            <option value="price_objection">💬 Fiyat İtirazı</option>
            <option value="follow_up">🕒 Takip Planlanan</option>
        </select>


        <button
            type="button"
            class="crm-reset"
            wire:click="resetFilters"
        >
            Temizle
        </button>

    </section>


    {{-- =========================================================
         CRM BODY
    ========================================================== --}}

    <section class="crm-shell">

        <div class="crm-list-area">

            <div class="crm-table-head">

                <div class="crm-table-title">
                    Müşteriler
                </div>

                <div class="crm-result-count">
                    {{ $this->customers->count() }} sonuç
                </div>

            </div>


            <div class="crm-list">

                @forelse ($this->customers as $customer)

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

                        $status =
                            $customer->lead_status
                            ?: 'new';

                        $temperature =
                            $customer->lead_temperature
                            ?: 'cold';

                        $score =
                            max(
                                0,
                                min(
                                    100,
                                    (int) $customer->lead_score
                                )
                            );
                    @endphp


                    <div
                        wire:key="crm-customer-{{ $customer->id }}"

                        wire:click="
                            selectCustomer(
                                {{ $customer->id }}
                            )
                        "

                        class="
                            customer-row
                            {{
                                $selectedCustomerId === $customer->id
                                    ? 'active'
                                    : ''
                            }}
                        "
                    >

                        <div class="customer-main">

                            <div class="customer-avatar">
                                {{ $initial }}
                            </div>

                            <div class="customer-copy">

                                <div class="customer-name">
                                    {{ $name }}
                                </div>

                                <div class="customer-phone">
                                    {{ $customer->whatsapp_number }}
                                </div>

                            </div>

                        </div>


                        <div>

                            <span
                                class="
                                    crm-pill
                                    status-{{ $status }}
                                "
                            >
                                {{ $this->statusLabel($status) }}
                            </span>

                        </div>


                        <div>

                            <span
                                class="
                                    crm-pill
                                    temp-{{ $temperature }}
                                "
                            >
                                {{ $this->temperatureLabel($temperature) }}
                            </span>

                        </div>


                        <div class="score-wrap">

                            <div class="score-number">
                                {{ $score }}/100
                            </div>

                            <div class="score-bar">

                                <span
                                    style="width: {{ $score }}%"
                                ></span>

                            </div>

                        </div>


                        <div class="crm-date">

                            {{
                                $customer->last_contact_at
                                    ? $customer->last_contact_at->diffForHumans()
                                    : $customer->updated_at?->diffForHumans()
                            }}

                        </div>

                    </div>

                @empty

                    <div class="crm-empty">
                        Bu filtrelere uygun müşteri bulunamadı.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- =====================================================
             CUSTOMER DETAIL
        ====================================================== --}}

        <aside class="crm-detail">

            @if ($this->selectedCustomer)

                @php
                    $selected =
                        $this->selectedCustomer;

                    $selectedName =
                        $selected->customer_name
                        ?: $selected->whatsapp_number;

                    $selectedInitial =
                        mb_strtoupper(
                            mb_substr(
                                $selectedName,
                                0,
                                1
                            )
                        );
                @endphp


                <div class="crm-detail-head">

                    <div class="crm-detail-avatar">
                        {{ $selectedInitial }}
                    </div>

                    <div class="crm-detail-name">
                        {{ $selectedName }}
                    </div>

                    <div class="crm-detail-phone">
                        {{ $selected->whatsapp_number }}
                    </div>


                    <div class="crm-detail-badges">

                        <span
                            class="
                                crm-pill
                                status-{{ $selected->lead_status ?: 'new' }}
                            "
                        >
                            {{
                                $this->statusLabel(
                                    $selected->lead_status
                                )
                            }}
                        </span>


                        <span
                            class="
                                crm-pill
                                temp-{{ $selected->lead_temperature ?: 'cold' }}
                            "
                        >
                            {{
                                $this->temperatureLabel(
                                    $selected->lead_temperature
                                )
                            }}
                        </span>


                        <span class="crm-pill channel-pill">

                            {{
                                $this->channelLabel(
                                    $selected->channel
                                )
                            }}

                        </span>

                    </div>

                </div>


                @php
                    $selectedTags =
                        is_array($selected->tags)
                            ? $selected->tags
                            : [];
                @endphp

                @if (count($selectedTags) > 0)

                    <div class="crm-form-section">

                        <div class="crm-form-title">
                            WAI Etiketleri
                        </div>

                        <div class="crm-tags">

                            @foreach ($selectedTags as $tag)

                                @php
                                    $tagClass =
                                        $tag === 'Öncelikli Lead'
                                            ? 'priority'
                                            : (
                                                in_array(
                                                    $tag,
                                                    [
                                                        'Riskli Lead',
                                                        'Kararsız',
                                                        'Fiyat İtirazı',
                                                    ],
                                                    true
                                                )
                                                    ? 'risk'
                                                    : (
                                                        in_array(
                                                            $tag,
                                                            [
                                                                'Satın Alma Niyeti',
                                                                'Acil',
                                                                'Geri Arama',
                                                            ],
                                                            true
                                                        )
                                                            ? 'intent'
                                                            : ''
                                                    )
                                            );
                                @endphp

                                <span class="crm-tag {{ $tagClass }}">
                                    {{ $tag }}
                                </span>

                            @endforeach

                        </div>

                    </div>

                @endif


                <div class="crm-form-section crm-section-standard">

                    <div class="crm-form-title">
                        Satış Aşaması
                    </div>


                    <div class="crm-quick-status">

                        @foreach (
                            [
                                'new' => 'Yeni',
                                'contacted' => 'Görüşme',
                                'qualified' => 'Nitelikli',
                                'proposal' => 'Teklif',
                                'won' => 'Kazanıldı',
                                'lost' => 'Kaybedildi',
                            ]
                            as $key => $label
                        )

                            <button
                                type="button"

                                wire:click="
                                    setLeadStatus(
                                        '{{ $key }}'
                                    )
                                "

                                class="
                                    {{
                                        $leadStatus === $key
                                            ? 'active'
                                            : ''
                                    }}
                                "
                            >
                                {{ $label }}
                            </button>

                        @endforeach

                    </div>

                </div>


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        Müşteri Bilgileri
                    </div>


                    <div class="crm-field">

                        <label>
                            Ad Soyad
                        </label>

                        <input
                            type="text"
                            wire:model="customerName"
                            placeholder="Müşteri adı"
                        >

                    </div>


                    <div class="crm-field">

                        <label>
                            Firma
                        </label>

                        <input
                            type="text"
                            wire:model="companyName"
                            placeholder="Firma adı"
                        >

                    </div>


                    <div class="crm-field">

                        <label>
                            E-posta
                        </label>

                        <input
                            type="email"
                            wire:model="customerEmail"
                            placeholder="ornek@firma.com"
                        >

                    </div>

                </div>


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        WAI Satış Asistanı
                    </div>

                    <div class="crm-ai-card">

                        <div class="crm-ai-card-head">

                            <div class="crm-ai-card-title">
                                🤖 WAI Müşteri Özeti
                            </div>

                            <button
                                type="button"
                                class="crm-ai-refresh"
                                wire:click="refreshAiSummary"
                                wire:loading.attr="disabled"
                                wire:target="refreshAiSummary"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="refreshAiSummary"
                                >
                                    Özeti Yenile
                                </span>

                                <span
                                    wire:loading
                                    wire:target="refreshAiSummary"
                                >
                                    Analiz ediliyor...
                                </span>
                            </button>

                        </div>

                        <div class="crm-ai-card-body">
                            @if (trim((string) $selected->ai_summary) !== '')
                                {{ $selected->ai_summary }}
                            @else
                                <span class="crm-ai-empty">
                                    Yeterli konuşma oluştuğunda WAI müşteri özetini otomatik hazırlayacak.
                                </span>
                            @endif
                        </div>

                        @if ($selected->ai_summary_updated_at)
                            <div class="crm-ai-meta">
                                Son güncelleme:
                                {{ $selected->ai_summary_updated_at->format('d.m.Y H:i') }}
                            </div>
                        @endif

                    </div>

                    <div class="crm-ai-card action">

                        <div class="crm-ai-card-title">
                            🎯 Önerilen Sonraki Aksiyon
                        </div>

                        <div class="crm-ai-card-body">
                            @if (trim((string) $selected->next_best_action) !== '')
                                {{ $selected->next_best_action }}
                            @else
                                <span class="crm-ai-empty">
                                    WAI konuşmayı analiz ettikten sonra satış ekibi için en değerli sonraki adımı burada gösterecek.
                                </span>
                            @endif
                        </div>

                    </div>

                </div>


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        Satış Tahmini
                    </div>

                    <div class="crm-field">

                        <label>
                            Tahmini Satış Tutarı (₺)
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="estimatedValue"
                            placeholder="Örn: 25000"
                        >

                    </div>

                    @if ($leadStatus === 'won')

                        <div class="crm-field">

                            <label>
                                Gerçekleşen Satış Tutarı (₺)
                            </label>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model="actualValue"
                                placeholder="Örn: 24500"
                            >

                        </div>

                    @endif


                    <div class="crm-forecast-card">

                        <div class="crm-forecast-value">
                            %{{ $this->selectedProbability }}
                        </div>

                        <div class="crm-forecast-label">
                            Tahmini Satış Olasılığı
                        </div>

                        <div class="crm-probability">
                            <span
                                style="width: {{ $this->selectedProbability }}%"
                            ></span>
                        </div>

                    </div>

                    <div
                        class="crm-forecast-card"
                        style="margin-top:10px;"
                    >

                        <div class="crm-forecast-value">
                            {{ number_format($this->selectedWeightedValue, 2, ',', '.') }} ₺
                        </div>

                        <div class="crm-forecast-label">
                            Olasılık Ağırlıklı Fırsat Değeri
                        </div>

                    </div>

                </div>


                <div class="crm-form-section crm-section-standard">

                    <div class="crm-form-title">
                        Lead Puanı
                    </div>


                    <div class="crm-score-row">

                        <div class="crm-field">

                            <label>
                                Satın alma potansiyeli
                            </label>

                            <input
                                class="crm-score-range"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                wire:model.live="leadScore"
                            >

                        </div>


                        <div class="crm-field">

                            <label>
                                Puan
                            </label>

                            <input
                                type="number"
                                min="0"
                                max="100"
                                wire:model="leadScore"
                            >

                        </div>

                    </div>

                </div>


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        Sorumlu Personel
                    </div>


                    <div class="crm-field">

                        <select
                            wire:model="assignedUserId"
                        >

                            <option value="">
                                Personel seçilmedi
                            </option>


                            @foreach ($this->teamMembers as $member)

                                <option value="{{ $member->id }}">
                                    {{ $member->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="crm-secondary-actions">

                        <button
                            type="button"
                            wire:click="assignToMe"
                        >
                            Kendime Ata
                        </button>

                        <button
                            type="button"
                            wire:click="clearAssignment"
                        >
                            Atamayı Kaldır
                        </button>

                    </div>

                </div>


                <div class="crm-form-section crm-section-standard">

                    <div class="crm-form-title">
                        Takip
                    </div>


                    <div class="crm-field">

                        <label>
                            Sonraki Takip Tarihi
                        </label>

                        <input
                            type="datetime-local"
                            wire:model="nextFollowUpAt"
                        >

                    </div>

                </div>


                @if ($leadStatus === 'lost')

                    <div class="crm-form-section">

                        <div class="crm-form-title">
                            Kaybedilme Nedeni
                        </div>

                        <div class="crm-field">

                            <textarea
                                wire:model="lostReason"
                                placeholder="Müşterinin neden kaybedildiğini yazın..."
                            ></textarea>

                        </div>

                    </div>

                @endif


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        CRM Notları
                    </div>


                    <div class="crm-field">

                        <textarea
                            wire:model="notes"
                            placeholder="Müşteri hakkında ekibiniz için not bırakın..."
                        ></textarea>

                    </div>

                </div>


                <div class="crm-form-section">

                    <div class="crm-form-title">
                        Müşteri Geçmişi
                    </div>

                    <div class="crm-timeline-filters">

                        @php
                            $activityFilters = [
                                'all' => 'Tümü',
                                'ai' => 'AI',
                                'staff' => 'Personel',
                                'sales' => 'Satış',
                                'follow_up' => 'Takip',
                                'notes' => 'Notlar',
                                'control' => 'Kontrol',
                            ];
                        @endphp

                        @foreach ($activityFilters as $key => $label)

                            <button
                                type="button"

                                wire:click="
                                    setActivityFilter(
                                        '{{ $key }}'
                                    )
                                "

                                class="
                                    crm-timeline-filter
                                    {{
                                        $activityFilter === $key
                                            ? 'active'
                                            : ''
                                    }}
                                "
                            >
                                {{ $label }}
                            </button>

                        @endforeach

                    </div>

                    <div class="crm-timeline">

                        @forelse ($this->selectedActivities as $activity)

                            <div
                                class="crm-timeline-item"
                                wire:key="crm-activity-{{ $activity->id }}"
                            >

                                <span
                                    class="
                                        crm-timeline-dot
                                        {{ $activity->type }}
                                    "
                                ></span>

                                <div class="crm-timeline-content">

                                    <div class="crm-timeline-title">
                                        {{ $activity->title }}
                                    </div>

                                    @if ($activity->description)

                                        <div class="crm-timeline-description">
                                            {{ $activity->description }}
                                        </div>

                                    @endif

                                    <div class="crm-timeline-meta">

                                        <span class="crm-timeline-actor">
                                            {{ $activity->actorName() }}
                                        </span>

                                        <span>
                                            ·
                                        </span>

                                        <span>
                                            {{ $activity->created_at?->format('d.m.Y H:i') }}
                                        </span>

                                    </div>

                                </div>

                            </div>

                        @empty

                            <div class="crm-timeline-empty">
                                {{
                                    $activityFilter === 'all'
                                        ? 'Bu müşteri için henüz CRM aktivitesi oluşmadı.'
                                        : 'Bu filtreye uygun aktivite bulunamadı.'
                                }}
                            </div>

                        @endforelse

                    </div>

                </div>


                <div class="crm-form-section">

                    <button
                        type="button"
                        class="crm-save"
                        wire:click="saveCustomer"
                        wire:loading.attr="disabled"
                        wire:target="saveCustomer"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M5 4h12l2 2v14H5V4Z"/>
                            <path d="M8 4v6h8V4"/>
                            <path d="M8 16h8"/>
                        </svg>

                        <span
                            wire:loading.remove
                            wire:target="saveCustomer"
                        >
                            CRM Bilgilerini Kaydet
                        </span>

                        <span
                            wire:loading
                            wire:target="saveCustomer"
                        >
                            Kaydediliyor...
                        </span>

                    </button>


                    <div class="crm-secondary-actions">

                        <a
                            href="{{ $this->inboxUrl() }}"
                        >
                            Gelen Kutusuna Git
                        </a>

                        <button
                            type="button"
                            wire:click="
                                setLeadScore(
                                    80
                                )
                            "
                        >
                            Sıcak Lead Yap
                        </button>

                    </div>

                </div>

            @else

                <div class="crm-empty">
                    Detaylarını görmek için bir müşteri seçin.
                </div>

            @endif

        </aside>

    </section>


    <div
        class="crm-toast"

        x-show="saved"

        x-transition

        x-cloak
    >

        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="12" cy="12" r="9"/>
            <path d="m8 12 3 3 5-6"/>
        </svg>

        Müşteri bilgileri kaydedildi.

    </div>

    <div
        class="crm-toast"
        x-show="summaryRefreshed"
        x-transition
        x-cloak
    >
        WAI müşteri özeti güncellendi.
    </div>

    <div
        class="crm-toast"
        x-show="summaryFailed"
        x-transition
        x-cloak
    >
        Müşteri özeti güncellenemedi.
    </div>

</div>

</x-filament-panels::page>