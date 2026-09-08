@php
    $insuranceTabs = [
        ['key' => 'operation', 'label' => 'Operasyon', 'caption' => 'Canlı dosyalar', 'href' => '/admin/sigorta-operasyon', 'icon' => '01'],
        ['key' => 'renewal', 'label' => 'Geri Kazanım', 'caption' => 'Yenileme takibi', 'href' => '/admin/sigorta-yenileme', 'icon' => '02'],
        ['key' => 'team', 'label' => 'Ekip', 'caption' => 'İş yükü & atama', 'href' => '/admin/sigorta-ekip', 'icon' => '03'],
        ['key' => 'management', 'label' => 'Yönetici', 'caption' => 'KPI & performans', 'href' => '/admin/sigorta-yonetici', 'icon' => '04'],
        ['key' => 'payment', 'label' => 'Teklif & Ödeme', 'caption' => 'Primden poliçeye', 'href' => '/admin/sigorta-teklif-odeme', 'icon' => '05'],
    ];
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

    <span class="tpc-global-live"><i></i> SİSTEM AKTİF</span>
</header>
