<x-filament-panels::page>
<style>
.fo{--ink:#122018;--muted:#718078;--line:#e4ebe7;width:100%;max-width:1180px;min-width:0;margin:auto;overflow-x:hidden}.fo *{box-sizing:border-box}.fo-hero{padding:24px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#fff8e8);margin-bottom:14px}.fo-hero small{color:#8a5a00;font-weight:900}.fo-hero h1{margin:6px 0;font-size:28px;font-weight:900;color:var(--ink)}.fo-hero p{margin:0;color:var(--muted);line-height:1.5}
.fo-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.fo-stat{padding:15px;border:1px solid var(--line);border-radius:17px;background:#fff}.fo-stat b{display:block;font-size:24px;color:var(--ink)}.fo-stat span{font-size:11px;color:var(--muted)}
.fo-tools{display:flex;justify-content:space-between;gap:10px;margin-bottom:14px}.fo-filters{display:flex;gap:6px;padding:5px;border:1px solid var(--line);border-radius:15px;background:#fff;overflow:auto}.fo-filter{padding:9px 12px;border:0;border-radius:10px;background:transparent;color:var(--muted);font-size:11px;font-weight:850;white-space:nowrap}.fo-filter.active{background:var(--ink);color:#fff}.fo-search{width:min(340px,100%);height:44px;border:1px solid var(--line);border-radius:13px;padding:0 13px;background:#fff}
.fo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.fo-card{min-width:0;max-width:100%;overflow:hidden;padding:18px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 9px 26px rgba(15,35,25,.035)}.fo-head{display:flex;min-width:0;justify-content:space-between;gap:10px}.fo-head>div:first-child{min-width:0}.fo-name{font-size:16px;font-weight:900;color:var(--ink)}.fo-property{margin-top:3px;font-size:12px;color:var(--muted)}.fo-score{width:56px;height:56px;flex:0 0 56px;display:grid;place-items:center;border-radius:50%;background:#122018;color:#fff;font-size:18px;font-weight:900}.fo-score.high{background:#087344}.fo-score.mid{background:#315b9c}.fo-score.blocked{background:#9a4141}
.fo-urgency{display:inline-flex;margin:9px 5px 0 0;padding:6px 9px;border-radius:999px;background:#fff0e8;color:#a94712;font-size:10px;font-weight:900}.fo-grade{display:inline-flex;margin-top:9px;padding:6px 9px;border-radius:999px;background:#fff4dd;color:#875900;font-size:10px;font-weight:900}.fo-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:11px}.fo-metrics div{min-width:0;padding:9px;border-radius:11px;background:#f7faf8}.fo-metrics small{display:block;font-size:8px;font-weight:900;color:var(--muted)}.fo-metrics b{display:block;margin-top:3px;font-size:11px;color:#35463d}
.fo-deal{margin-top:10px;padding:12px;border-radius:13px;background:#fffaf0;border:1px solid #f2e3bd}.fo-deal-title{font-size:9px;font-weight:900;color:#8a5a00}.fo-deal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:8px}.fo-deal-grid>div{min-width:0}.fo-deal-grid small{display:block;font-size:8px;color:var(--muted);font-weight:900}.fo-deal-grid b{display:block;margin-top:3px;font-size:11px;line-height:1.35;color:var(--ink);overflow-wrap:anywhere}.fo-commercial-action{margin:8px 0 0;font-size:11px;line-height:1.45;color:#5f543d;overflow-wrap:anywhere}.fo-commission{display:flex;gap:6px;align-items:center;margin-top:8px}.fo-commission input{width:95px;height:36px;padding:0 9px;border:1px solid var(--line);border-radius:9px;background:#fff}.fo-commission button{height:36px;padding:0 10px;border:0;border-radius:9px;background:#8a5a00;color:#fff;font-size:10px;font-weight:900}.fo-action{margin-top:10px;padding:12px;border-left:3px solid #22c77a;border-radius:10px;background:#f4fbf7}.fo-action small{font-size:9px;font-weight:900;color:#087344}.fo-action p{margin:4px 0 0;font-size:12px;line-height:1.5;color:#405149}.fo-foot{display:flex;justify-content:space-between;align-items:center;margin-top:11px}.fo-foot span{font-size:10px;color:var(--muted)}.fo-open{padding:8px 11px;border-radius:10px;background:#eef4ff;color:#315b9c;text-decoration:none;font-size:10px;font-weight:900}.fo-empty{grid-column:1/-1;padding:45px;text-align:center;border:1px dashed var(--line);border-radius:20px;color:var(--muted)}
@media(max-width:760px){
.fo{max-width:100%}.fo-hero{padding:17px;border-radius:17px}.fo-hero h1{font-size:22px}.fo-hero p{font-size:13px}
.fo-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.fo-stat{min-width:0;padding:13px}.fo-stat b{font-size:21px}.fo-stat span{font-size:10px;line-height:1.25}
.fo-tools{min-width:0;flex-direction:column}.fo-filters{width:100%;max-width:100%;scrollbar-width:none;-webkit-overflow-scrolling:touch}.fo-filters::-webkit-scrollbar{display:none}.fo-filter{flex:0 0 auto;padding:9px 11px}.fo-search{width:100%;max-width:100%;font-size:16px}
.fo-grid{grid-template-columns:minmax(0,1fr)}.fo-card{width:100%;padding:14px;border-radius:17px}.fo-score{width:46px;height:46px;flex-basis:46px;font-size:16px}.fo-name{font-size:15px}
.fo-metrics{gap:5px}.fo-metrics div{padding:8px 6px}.fo-metrics small{font-size:7px;line-height:1.2;overflow-wrap:anywhere}.fo-metrics b{font-size:10px}
.fo-deal{padding:11px}.fo-deal-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 8px}.fo-deal-grid small{font-size:7px;line-height:1.2}.fo-deal-grid b{font-size:10px}.fo-commercial-action{font-size:11px}
.fo-commission{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:7px}.fo-commission input{width:100%;min-width:0;font-size:16px}.fo-commission button{width:100%;padding:0 8px}
.fo-action{padding:11px}.fo-action p{font-size:11px}.fo-foot{min-width:0;gap:8px}.fo-foot span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.fo-open{flex:0 0 auto}
}
@media(max-width:380px){.fo-deal-grid{grid-template-columns:1fr}.fo-metrics{grid-template-columns:1fr 1fr}.fo-metrics div:last-child{grid-column:1/-1}.fo-commission{grid-template-columns:1fr}}
</style>
<div class="fo">
    <section class="fo-hero">
        <small>DOĞRULANMIŞ AVANTAJ · DOSYA KALİTESİ · GERÇEK YATIRIMCI</small>
        <h1>Fırsat Motoru</h1>
        <p>Acil satıcıları iletişim önceliğinde yükseltir; fiyat avantajı, dosya güvenliği ve gerçek yatırımcı eşleşmesiyle birlikte sıradaki doğru aksiyonu gösterir.</p>
    </section>

    <div class="fo-stats">
        <div class="fo-stat"><b>{{ $this->stats['exceptional'] }}</b><span>Çok güçlü fırsat</span></div>
        <div class="fo-stat"><b>{{ $this->stats['strong'] }}</b><span>Güçlü fırsat</span></div>
        <div class="fo-stat"><b>{{ $this->stats['preparation'] }}</b><span>Hazırlanması gereken</span></div>
        <div class="fo-stat"><b>{{ $this->stats['blocked'] }}</b><span>Doğrulama bekleyen</span></div>
    </div>

    <div class="fo-tools">
        <div class="fo-filters">
            @foreach(['priority'=>'Öncelik listesi','actionable'=>'Hazır fırsatlar','preparation'=>'Eksik belge / hazırlık','blocked'=>'Blokeli','all'=>'Tümü'] as $key=>$label)
                <button class="fo-filter {{ $filter===$key?'active':'' }}" wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>
        <input class="fo-search" wire:model.live.debounce.300ms="search" placeholder="Satıcı, telefon veya portföy ara…">
    </div>

    <div class="fo-grid">
        @forelse($this->opportunities as $item)
            @php
                $scoreClass = $item['state']==='blocked' ? 'blocked' : ($item['score']>=65 ? 'high' : ($item['score']>=45 ? 'mid' : ''));
                $gradeLabel = match($item['grade']) {
                    'exceptional'=>'ÇOK GÜÇLÜ FIRSAT','strong'=>'GÜÇLÜ FIRSAT','watch'=>'TAKİP ET',
                    'blocked'=>'DOĞRULAMA BEKLİYOR',default=>'DÜŞÜK ÖNCELİK',
                };
            @endphp
            <article class="fo-card" wire:key="opportunity-{{ $item['id'] }}">
                <div class="fo-head">
                    <div><div class="fo-name">{{ $item['name'] }}</div><div class="fo-property">{{ $item['property'] }}</div>@if($item['urgency_rank'] > 1)<span class="fo-urgency">{{ $item['urgency_label'] }}{{ $item['sale_timeline'] ? ' · '.$item['sale_timeline'] : '' }}</span>@endif<span class="fo-grade">{{ $gradeLabel }}</span></div>
                    <div class="fo-score {{ $scoreClass }}">{{ $item['score'] }}</div>
                </div>
                <div class="fo-metrics">
                    <div><small>FİYAT AVANTAJI</small><b>{{ $item['discount']===null?'—':(($item['discount']>=0?'+':'').$item['discount'].'%') }}</b></div>
                    <div><small>YATIRIMCI</small><b>{{ $item['candidate_count'] }} aday</b></div>
                    <div><small>DOSYA</small><b>%{{ $item['completeness'] }}</b></div>
                </div>
                <div class="fo-deal">
                    <div class="fo-deal-title">TİCARİ FIRSAT HESABI</div>
                    <div class="fo-deal-grid">
                        <div><small>SATICI BEKLENTİSİ</small><b>{{ $item['asking_price'] ? number_format($item['asking_price'],0,',','.').' TL' : 'Eksik' }}</b></div>
                        <div><small>GERÇEKÇİ SATIŞ</small><b>{{ $item['realistic_sale_max'] ? number_format($item['realistic_sale_min'] ?: $item['realistic_sale_max'],0,',','.').'–'.number_format($item['realistic_sale_max'],0,',','.').' TL' : 'Değerleme gerekli' }}</b></div>
                        <div><small>HEDEF YATIRIMCI BANDI</small><b>{{ $item['negotiation_target_max'] ? number_format($item['negotiation_target_min'] ?: $item['negotiation_target_max'],0,',','.').'–'.number_format($item['negotiation_target_max'],0,',','.').' TL' : 'Değerleme gerekli' }}</b></div>
                        <div><small>PAZARLIK FARKI</small><b>{{ $item['gap_amount']===null ? '—' : number_format($item['gap_amount'],0,',','.').' TL'.($item['gap_percent'] ? ' · %'.$item['gap_percent'] : '') }}</b></div>
                        <div><small>TAHMİNİ KOMİSYON</small><b>{{ $item['expected_commission'] ? number_format($item['expected_commission'],0,',','.').' TL' : 'Oran girilmeli' }}</b></div>
                        <div><small>UYGUN YATIRIMCI</small><b>{{ $item['candidate_count'] }} kişi</b></div>
                    </div>
                    <p class="fo-commercial-action">{{ $item['commercial_action'] }}</p>
                    <div class="fo-commission">
                        <input type="number" min="0.1" max="20" step="0.1" wire:model="commissionRates.{{ $item['id'] }}" placeholder="{{ $item['commission_rate'] ? '%'.$item['commission_rate'] : 'Komisyon %' }}">
                        <button wire:click="saveCommission({{ $item['id'] }})">Oranı kaydet</button>
                    </div>
                </div>
                <div class="fo-action"><small>KONUŞMA ÖZETİ</small><p>{{ $item['summary'] }}</p></div>
                <div class="fo-action"><small>SIRADAKİ AKSİYON</small><p>{{ $item['action'] }}</p></div>
                <div class="fo-foot"><span>{{ $item['phone'] ?: 'Telefon yok' }}</span>@if($item['customer_url'])<a class="fo-open" href="{{ $item['customer_url'] }}">Dosyayı aç</a>@endif</div>
            </article>
        @empty
            <div class="fo-empty">Bu filtrede henüz portföy bulunmuyor.</div>
        @endforelse
    </div>
</div>
</x-filament-panels::page>
