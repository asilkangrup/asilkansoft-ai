@php
    $insuranceTabs = [
        ['key' => 'operation', 'label' => 'Operasyon', 'caption' => 'Canlı dosyalar', 'href' => '/admin/sigorta-operasyon', 'icon' => '01'],
        ['key' => 'renewal', 'label' => 'Geri Kazanım', 'caption' => 'Yenileme takibi', 'href' => '/admin/sigorta-yenileme', 'icon' => '02'],
        ['key' => 'team', 'label' => 'Ekip', 'caption' => 'İş yükü & atama', 'href' => '/admin/sigorta-ekip', 'icon' => '03'],
        ['key' => 'management', 'label' => 'Yönetici', 'caption' => 'KPI & performans', 'href' => '/admin/sigorta-yonetici', 'icon' => '04'],
        ['key' => 'payment', 'label' => 'Teklif & Ödeme', 'caption' => 'Primden poliçeye', 'href' => '/admin/sigorta-teklif-odeme', 'icon' => '05'],
    ];

    $insuranceBot = auth()->user()
        ?->aiBots()
        ->where('business_sector', 'insurance')
        ->latest('id')
        ->first();

    $whatsAppStatus = strtolower(trim((string) ($insuranceBot?->whatsapp_status ?? 'disconnected')));
    $whatsAppConnected = in_array($whatsAppStatus, ['connected', 'open'], true);
    $whatsAppLabel = $whatsAppConnected
        ? 'WHATSAPP BAĞLI'
        : ($whatsAppStatus === 'connecting' ? 'QR HAZIR' : 'WHATSAPP BAĞLA');
@endphp

<style>
.tpc-global-header{position:relative;z-index:50;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:22px;margin-bottom:18px;padding:14px 16px;border:1px solid rgba(125,211,252,.14);border-radius:20px;background:linear-gradient(135deg,rgba(13,27,46,.96),rgba(8,20,35,.96));box-shadow:0 18px 55px rgba(0,0,0,.24),inset 0 1px 0 rgba(255,255,255,.035);backdrop-filter:blur(18px)}
.tpc-global-brand{display:flex;align-items:center;gap:12px;min-width:max-content;text-decoration:none}
.tpc-global-mark{position:relative;width:48px;height:48px;border-radius:15px;display:grid;place-items:center;color:#fff;font-size:14px;font-weight:950;letter-spacing:.03em;background:linear-gradient(145deg,#2f6df6,#06b6d4);box-shadow:0 12px 30px rgba(37,99,235,.32)}
.tpc-global-mark:after{content:"";position:absolute;inset:5px;border:1px solid rgba(255,255,255,.22);border-radius:11px}
.tpc-global-brand-copy b{display:block;color:#f8fbff;font-size:14px;letter-spacing:.02em}.tpc-global-brand-copy span{display:block;margin-top:3px;color:#7691aa;font-size:9px;letter-spacing:.04em}
.tpc-global-nav{display:grid;grid-template-columns:repeat(5,minmax(105px,1fr));gap:7px}
.tpc-global-nav a{position:relative;display:flex;align-items:center;gap:8px;min-height:48px;padding:8px 10px;border:1px solid #24384e;border-radius:13px;color:#94aac0;background:rgba(7,18,31,.72);text-decoration:none;transition:transform .16s ease,border-color .16s ease,background .16s ease}
.tpc-global-nav a:hover{transform:translateY(-1px);border-color:#3a5977;background:#102239;color:#e6f3ff}
.tpc-global-nav a.is-active{border-color:rgba(56,189,248,.46);color:#fff;background:linear-gradient(135deg,rgba(37,99,235,.36),rgba(8,145,178,.24));box-shadow:0 9px 24px rgba(2,132,199,.13)}
.tpc-global-tab-icon{flex:0 0 27px;width:27px;height:27px;border-radius:9px;display:grid;place-items:center;color:#72dff7;background:#142940;font-size:8px;font-weight:950}
.tpc-global-nav a.is-active .tpc-global-tab-icon{color:#fff;background:linear-gradient(135deg,#2563eb,#0891b2)}
.tpc-global-tab-copy b{display:block;font-size:9px;line-height:1.15}.tpc-global-tab-copy span{display:block;margin-top:3px;color:#668097;font-size:7px;line-height:1.1}.tpc-global-nav a.is-active .tpc-global-tab-copy span{color:#9fdcf0}
.tpc-global-live{display:flex;align-items:center;gap:7px;min-width:max-content;padding:8px 10px;border:1px solid rgba(16,185,129,.2);border-radius:999px;color:#9ee7c8;background:rgba(16,185,129,.07);font-size:8px;font-weight:950;letter-spacing:.06em}.tpc-global-live i{width:7px;height:7px;border-radius:50%;background:#34d399;box-shadow:0 0 0 5px rgba(52,211,153,.08)}
@media(max-width:1180px){.tpc-global-header{grid-template-columns:auto 1fr}.tpc-global-live{display:none}.tpc-global-nav{grid-column:1/-1}}
@media(max-width:720px){.tpc-global-header{display:flex;flex-direction:column;align-items:stretch;gap:12px;padding:13px;border-radius:17px}.tpc-global-brand{justify-content:flex-start}.tpc-global-mark{width:43px;height:43px}.tpc-global-nav{display:grid;grid-template-columns:1fr 1fr}.tpc-global-nav a{min-height:46px}.tpc-global-nav a:last-child{grid-column:1/-1}.tpc-global-tab-copy b{font-size:9px}.tpc-global-tab-copy span{font-size:7px}}

/* TPC readable premium scale — shared by all five insurance modules */
.fi-header,.fi-page-header{display:none!important}
.tpc-global-header{overflow:hidden;gap:26px;padding:18px 20px;border-color:rgba(103,211,255,.22);border-radius:24px;background:radial-gradient(circle at 12% 0,rgba(37,99,235,.16),transparent 34%),linear-gradient(135deg,rgba(12,29,49,.98),rgba(5,17,31,.98));box-shadow:0 24px 70px rgba(0,0,0,.32),0 0 0 1px rgba(14,165,233,.035),inset 0 1px 0 rgba(255,255,255,.06)}
.tpc-global-header:before{content:"";position:absolute;inset:0 auto auto 8%;width:44%;height:1px;background:linear-gradient(90deg,transparent,#38bdf8,transparent);opacity:.65}
.tpc-global-brand{gap:15px}.tpc-global-mark{width:58px;height:58px;border-radius:18px;font-size:17px;box-shadow:0 16px 38px rgba(37,99,235,.38),inset 0 1px 0 rgba(255,255,255,.22)}.tpc-global-mark:after{inset:6px;border-radius:13px}
.tpc-global-brand-copy b{font-size:18px;letter-spacing:.01em}.tpc-global-brand-copy span{margin-top:5px;font-size:11px;line-height:1.35;letter-spacing:.055em}
.tpc-global-nav{gap:10px}.tpc-global-nav a{min-height:62px;padding:11px 13px;border-color:rgba(85,121,153,.38);border-radius:16px;background:linear-gradient(145deg,rgba(9,25,42,.9),rgba(5,17,30,.88));box-shadow:inset 0 1px 0 rgba(255,255,255,.025)}
.tpc-global-nav a:hover{transform:translateY(-2px);border-color:rgba(103,211,255,.55);box-shadow:0 13px 30px rgba(0,0,0,.24),inset 0 1px 0 rgba(255,255,255,.05)}
.tpc-global-nav a.is-active{border-color:rgba(56,189,248,.72);background:radial-gradient(circle at 12% 0,rgba(103,232,249,.18),transparent 38%),linear-gradient(135deg,rgba(37,99,235,.48),rgba(8,145,178,.3));box-shadow:0 14px 34px rgba(2,132,199,.2),inset 0 1px 0 rgba(255,255,255,.08)}
.tpc-global-tab-icon{flex-basis:36px;width:36px;height:36px;border-radius:11px;font-size:11px}.tpc-global-tab-copy b{font-size:13px;line-height:1.2}.tpc-global-tab-copy span{margin-top:4px;font-size:10px;line-height:1.25}.tpc-global-live{padding:10px 13px;font-size:10px}

/* Large, legible type throughout the product */
.tpc-launch-head span{font-size:11px}.tpc-launch-head h2{font-size:27px}.tpc-launch-head p{font-size:13px;line-height:1.65}
.tpc-module-card{min-height:156px;padding:18px!important;border-radius:20px!important;background:linear-gradient(145deg,rgba(15,38,63,.96),rgba(7,24,41,.96))!important;box-shadow:0 16px 38px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.035)}
.tpc-module-card:hover{transform:translateY(-4px);border-color:rgba(56,189,248,.55)!important;box-shadow:0 22px 44px rgba(0,0,0,.28),0 0 28px rgba(14,165,233,.07)}
.tpc-module-card i{width:38px;height:38px;border-radius:12px;font-size:11px}.tpc-module-card b{font-size:14px}.tpc-module-card span{margin-top:7px;font-size:11px;line-height:1.55}.tpc-module-card strong{font-size:23px}.tpc-module-card em{font-size:10px}

.eyebrow,.ren-eyebrow{font-size:12px}.hero p,.ren-hero p{font-size:16px;line-height:1.75}.badge,.ren-badge{font-size:11px}
.kpi,.ren-kpi{border-color:rgba(81,116,148,.44)!important;background:linear-gradient(145deg,rgba(16,35,57,.96),rgba(7,23,39,.96))!important;box-shadow:0 16px 38px rgba(0,0,0,.17),inset 0 1px 0 rgba(255,255,255,.035)}
.kpi small,.ren-kpi small{font-size:12px}.kpi strong,.ren-kpi strong{font-size:34px}.kpi em,.ren-kpi span{font-size:11px;line-height:1.45}
.panel,.ren-card{border-color:rgba(76,111,143,.42)!important;box-shadow:0 22px 54px rgba(0,0,0,.2),inset 0 1px 0 rgba(255,255,255,.025)}
.panel-head h2,.head h2,.ren-head h2{font-size:19px}.panel-head p,.head p,.ren-head p{font-size:12px;line-height:1.6}
.field label,.ren-field label,.closure-box label{font-size:12px}.input,.select,.ren-input{font-size:14px;min-height:44px}.btn,.ren-btn{font-size:12px}
.note{font-size:12px}.filters button,.ren-filter button{font-size:11px}
.case b,.card b,.row b,.case-row b,.ren-main b{font-size:13px}.case span,.card span,.row span,.case-row span,.ren-main span{font-size:11px;line-height:1.45}
.status,.ren-status{font-size:10px!important}.case-actions button,.case-actions a,.actions button,.ren-actions button{font-size:10px}
.member h3{font-size:15px}.member p{font-size:11px}.stat small{font-size:10px}.stat b{font-size:18px}
.flow-step b{font-size:13px}.flow-step span{font-size:11px}.flow-step i{width:34px;height:34px;font-size:12px}
.quote-summary{font-size:22px}.quote-summary small,.quote-option small{font-size:10px}.quote-option strong{font-size:20px}.quote-option button,.closure-box button{font-size:10px}.policy-line{font-size:11px}
.drawer-top p{font-size:12px}.progress-step small{font-size:10px}.progress-step b{font-size:11px}.section h3{font-size:14px}.info small{font-size:10px}.info b,.quote b{font-size:12px}.quote span,.event span{font-size:10px}.event b{font-size:11px}.drawer-actions button,.drawer-actions a{font-size:11px}

@media(max-width:1180px){.tpc-global-header{padding:17px}.tpc-global-nav a{min-height:58px}.tpc-global-tab-copy b{font-size:13px}.tpc-global-tab-copy span{font-size:10px}}
@media(max-width:720px){.tpc-global-header{padding:15px;border-radius:20px}.tpc-global-brand-copy b{font-size:17px}.tpc-global-brand-copy span{font-size:10px}.tpc-global-nav{gap:8px}.tpc-global-nav a{min-height:62px;padding:10px}.tpc-global-tab-icon{flex-basis:33px;width:33px;height:33px}.tpc-global-tab-copy b{font-size:12px}.tpc-global-tab-copy span{font-size:9px}.tpc-launch-head h2{font-size:24px}.tpc-launch-head p{font-size:12px}.tpc-module-card{min-height:142px}.hero p,.ren-hero p{font-size:14px}.kpi small,.ren-kpi small{font-size:11px}.panel-head p,.head p,.ren-head p{font-size:11px}}


/* CAMLA-inspired TPC palette: obsidian, violet and electric blue */
:root{--tpc-ink:#050814;--tpc-panel:#111827;--tpc-panel-deep:#070b18;--tpc-violet:#6b5cff;--tpc-purple:#8b5cf6;--tpc-lilac:#a78bfa;--tpc-blue:#60a5fa;--tpc-sky:#38bdf8;--tpc-text:#f8faff;--tpc-muted:#9da7bb;--tpc-line:rgba(148,163,184,.18)}
html,.fi-body,.fi-main{background:#050814!important}
.tpc-shell,.ren-shell,.team-shell,.shell{color:var(--tpc-text)!important;background:radial-gradient(circle at 88% 5%,rgba(107,92,255,.2),transparent 29%),radial-gradient(circle at 8% 23%,rgba(56,189,248,.08),transparent 24%),linear-gradient(180deg,#050814 0%,#070b18 48%,#050814 100%)!important}
.tpc-global-header{border-color:rgba(139,92,246,.3);background:radial-gradient(circle at 8% 0,rgba(139,92,246,.2),transparent 31%),linear-gradient(135deg,rgba(7,11,24,.98),rgba(5,8,20,.98));box-shadow:0 24px 70px rgba(0,0,0,.42),0 0 45px rgba(107,92,255,.08),inset 0 1px 0 rgba(255,255,255,.06)}
.tpc-global-header:before{background:linear-gradient(90deg,transparent,#8b5cf6,#60a5fa,transparent)}
.tpc-global-mark{background:linear-gradient(145deg,#8b5cf6,#6b5cff 55%,#60a5fa);box-shadow:0 16px 40px rgba(107,92,255,.42),0 0 30px rgba(96,165,250,.12),inset 0 1px 0 rgba(255,255,255,.24)}
.tpc-global-brand-copy span{color:#8d98ad}.tpc-global-nav a{border-color:rgba(148,163,184,.18);color:#aeb7c8;background:linear-gradient(145deg,rgba(17,24,39,.9),rgba(7,11,24,.94))}
.tpc-global-nav a:hover{border-color:rgba(139,92,246,.55);background:linear-gradient(145deg,rgba(32,28,71,.92),rgba(12,18,38,.94));color:#fff;box-shadow:0 15px 34px rgba(0,0,0,.3),0 0 24px rgba(107,92,255,.1)}
.tpc-global-nav a.is-active{border-color:rgba(139,92,246,.72);background:radial-gradient(circle at 8% 0,rgba(255,255,255,.1),transparent 33%),linear-gradient(135deg,rgba(107,92,255,.72),rgba(76,92,255,.46) 62%,rgba(56,189,248,.2));box-shadow:0 16px 38px rgba(107,92,255,.25),inset 0 1px 0 rgba(255,255,255,.12)}
.tpc-global-tab-icon{color:#c4b5fd;background:rgba(107,92,255,.16)}.tpc-global-nav a.is-active .tpc-global-tab-icon{background:linear-gradient(135deg,#8b5cf6,#4c5cff);box-shadow:0 8px 22px rgba(107,92,255,.35)}.tpc-global-tab-copy span{color:#7f8a9e}.tpc-global-nav a.is-active .tpc-global-tab-copy span{color:#d8d7ff}

.tpc-launch,.hero,.ren-hero{border-color:rgba(139,92,246,.22)!important;background:radial-gradient(circle at 88% 10%,rgba(107,92,255,.18),transparent 32%),radial-gradient(circle at 72% 72%,rgba(56,189,248,.055),transparent 24%),linear-gradient(145deg,rgba(7,11,24,.98),rgba(9,14,30,.98))!important;box-shadow:0 28px 80px rgba(0,0,0,.32),inset 0 1px 0 rgba(255,255,255,.04)!important}
.tpc-launch-head span,.eyebrow,.ren-eyebrow{color:#a78bfa!important}
.tpc-launch-head h2,.hero h1,.ren-hero h1{color:#f8faff!important}.hero p,.ren-hero p,.tpc-launch-head p{color:#b6bfd0!important}
.tpc-module-card,.kpi,.ren-kpi,.panel,.ren-card,.member,.card{border-color:rgba(148,163,184,.17)!important;background:linear-gradient(145deg,rgba(17,24,39,.96),rgba(7,11,24,.98))!important;box-shadow:0 18px 46px rgba(0,0,0,.24),inset 0 1px 0 rgba(255,255,255,.035)!important}
.tpc-module-card:hover,.member:hover,.card:hover{border-color:rgba(139,92,246,.48)!important;box-shadow:0 24px 52px rgba(0,0,0,.32),0 0 30px rgba(107,92,255,.08)!important}
.tpc-module-card.active{border-color:rgba(139,92,246,.62)!important;background:radial-gradient(circle at 12% 0,rgba(139,92,246,.16),transparent 34%),linear-gradient(145deg,rgba(24,27,57,.98),rgba(8,13,28,.98))!important}
.tpc-module-card i,.flow-step i{color:#c4b5fd!important;background:rgba(107,92,255,.16)!important}.tpc-module-card em,.tpc-launch a,.flow-step b{color:#8b7cff!important}
.kpi strong,.ren-kpi strong,.metric,.quote-summary,.quote-option strong{color:#fff!important}.kpi small,.kpi em,.ren-kpi small,.ren-kpi span,.panel-head p,.head p,.ren-head p{color:#8f9bb0!important}
.panel-head,.head,.ren-head{border-color:rgba(148,163,184,.14)!important}
.input,.select,.ren-input{border-color:rgba(148,163,184,.2)!important;background:#070b18!important}.input:focus,.select:focus,.ren-input:focus{border-color:#8b5cf6!important;box-shadow:0 0 0 3px rgba(107,92,255,.12)!important}
.btn,.ren-btn,.actions .primary,.case-actions .primary{background:linear-gradient(135deg,#8b5cf6,#6b5cff 58%,#4c5cff)!important;border-color:transparent!important;box-shadow:0 12px 28px rgba(107,92,255,.22)!important}
.filters button.active,.ren-filter button.active{color:#fff!important;border-color:rgba(139,92,246,.6)!important;background:rgba(107,92,255,.22)!important}
.badge:not(.success),.ren-badge{color:#ddd6fe!important;border-color:rgba(139,92,246,.36)!important;background:rgba(107,92,255,.1)!important}
.note{color:#c7d2fe!important;border-color:rgba(107,92,255,.24)!important;background:rgba(107,92,255,.07)!important}
.progress-step.active{border-color:#8b5cf6!important;background:rgba(107,92,255,.14)!important}
a:focus-visible,button:focus-visible{outline:2px solid #a78bfa!important;outline-offset:3px}
@media(max-width:720px){.tpc-shell,.ren-shell,.team-shell,.shell{background:radial-gradient(circle at 100% 0,rgba(107,92,255,.18),transparent 24%),#050814!important}.tpc-global-header{box-shadow:0 18px 48px rgba(0,0,0,.38),0 0 28px rgba(107,92,255,.07)}}


.tpc-global-tools{display:flex;align-items:center;gap:8px;min-width:max-content}
.tpc-whatsapp-link{display:flex;align-items:center;gap:7px;padding:10px 12px;border:1px solid rgba(139,92,246,.38);border-radius:999px;color:#ddd6fe;background:rgba(107,92,255,.1);font-size:10px;font-weight:950;letter-spacing:.045em;text-decoration:none;transition:transform .16s ease,border-color .16s ease,background .16s ease}
.tpc-whatsapp-link:hover{transform:translateY(-1px);border-color:rgba(139,92,246,.72);color:#fff;background:rgba(107,92,255,.22)}
.tpc-whatsapp-link i{width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 5px rgba(245,158,11,.08)}
.tpc-whatsapp-link.is-connected{color:#a7f3d0;border-color:rgba(16,185,129,.28);background:rgba(16,185,129,.08)}
.tpc-whatsapp-link.is-connected i{background:#34d399;box-shadow:0 0 0 5px rgba(52,211,153,.09)}
@media(max-width:1180px){.tpc-global-tools{position:absolute;right:17px;top:19px}.tpc-global-live{display:none}}
@media(max-width:720px){.tpc-global-tools{position:static;width:100%}.tpc-whatsapp-link{justify-content:center;width:100%;min-height:42px}.tpc-global-live{display:none}}

</style>

<header class="tpc-global-header">
    <a class="tpc-global-brand" href="/admin/sigorta-operasyon">
        <span class="tpc-global-mark">TPC</span>
        <span class="tpc-global-brand-copy">
            <b>TPC Insurance OS</b>
            <span>DOĞUŞ TOPÇU SİGORTA • OPERASYON PLATFORMU</span>
        </span>
    </a>

    <nav class="tpc-global-nav" aria-label="Sigorta ürün menüsü">
        @foreach($insuranceTabs as $tab)
            <a class="{{ ($active ?? '') === $tab['key'] ? 'is-active' : '' }}" href="{{ $tab['href'] }}">
                <span class="tpc-global-tab-icon">{{ $tab['icon'] }}</span>
                <span class="tpc-global-tab-copy">
                    <b>{{ $tab['label'] }}</b>
                    <span>{{ $tab['caption'] }}</span>
                </span>
            </a>
        @endforeach
    </nav>

    <div class="tpc-global-tools">
        @if($insuranceBot)
            <a class="tpc-whatsapp-link {{ $whatsAppConnected ? 'is-connected' : '' }}" href="/admin/ai-bots/{{ $insuranceBot->id }}/whatsapp">
                <i></i> {{ $whatsAppLabel }}
            </a>
        @endif
        <span class="tpc-global-live"><i></i> SİSTEM AKTİF</span>
    </div>
</header>
