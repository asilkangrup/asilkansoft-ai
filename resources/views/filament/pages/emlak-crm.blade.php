<x-filament-panels::page>
<style>
.ec{--g:#22c77a;--ink:#122018;--muted:#738179;--line:#e4ebe7;max-width:1180px;margin:auto}
.ec-hero{padding:24px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f3fcf7);margin-bottom:14px}.ec-hero small{color:#087344;font-weight:850}.ec-hero h1{margin:6px 0;font-size:28px;font-weight:900;color:var(--ink)}.ec-hero p{margin:0;color:var(--muted)}
.ec-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.ec-stat{padding:15px;border:1px solid var(--line);border-radius:17px;background:#fff}.ec-stat b{display:block;font-size:24px;color:var(--ink)}.ec-stat span{font-size:12px;color:var(--muted)}
.ec-toolbar{display:flex;gap:10px;justify-content:space-between;margin-bottom:14px}.ec-tabs{display:flex;gap:7px;padding:5px;border:1px solid var(--line);border-radius:15px;background:#fff}.ec-tab{border:0;border-radius:11px;background:transparent;padding:10px 14px;font-size:12px;font-weight:850;color:var(--muted);cursor:pointer}.ec-tab.active{background:#122018;color:#fff}
.ec-search{width:min(360px,100%);height:46px;border:1px solid var(--line);border-radius:14px;padding:0 14px;background:#fff;font-size:14px}
.ec-region-help{margin:-2px 0 14px;padding:14px 16px;border:1px solid #d8eee3;border-radius:16px;background:#f4fbf7;color:#405149;font-size:13px;line-height:1.5}.ec-region-help b{color:#087344}.ec-region-counts{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.ec-region-counts span{padding:6px 9px;border-radius:999px;background:#fff;border:1px solid #dfe9e3;font-size:11px;font-weight:850;color:#405149}
.ec-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ec-card{padding:18px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 9px 25px rgba(15,40,25,.035)}.ec-top{display:flex;justify-content:space-between;gap:10px}.ec-name{font-size:17px;font-weight:900;color:var(--ink)}.ec-title{margin-top:3px;font-size:13px;color:var(--muted)}
.ec-badges{display:flex;align-items:flex-start;gap:6px;flex-wrap:wrap;justify-content:flex-end}.ec-role{height:29px;padding:0 9px;display:inline-flex;align-items:center;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap;background:#eef7f2;color:#315744}
.ec-status{height:29px;padding:0 9px;display:inline-flex;align-items:center;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap}.ec-status.green{background:#e9faf2;color:#087344}.ec-status.orange{background:#fff4dd;color:#875900}.ec-status.blue{background:#eef4ff;color:#315b9c}.ec-status.red{background:#fff0f0;color:#a43838}
.ec-data{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:13px}.ec-data div{padding:11px;border-radius:12px;background:#f7faf8}.ec-data small{display:block;color:var(--muted);font-size:10px;font-weight:800}.ec-data b{display:block;margin-top:4px;color:#304139;font-size:12px}
.ec-summary{margin-top:10px;padding:12px;border-radius:10px;background:#f7faf8}.ec-summary small{font-size:10px;font-weight:900;color:#53645b}.ec-summary p{margin:4px 0 0;font-size:12px;line-height:1.5;color:#405149}
.ec-action{margin-top:10px;padding:12px;border-left:3px solid var(--g);border-radius:10px;background:#f4fbf7}.ec-action small{font-size:10px;font-weight:900;color:#087344}.ec-action p{margin:4px 0 0;font-size:12px;line-height:1.5;color:#405149}
.ec-note{margin-top:9px;font-size:11px;line-height:1.45;color:var(--muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.ec-foot{display:flex;align-items:center;justify-content:space-between;margin-top:12px}.ec-foot span{font-size:11px;color:var(--muted)}.ec-open{padding:9px 12px;border-radius:11px;background:#eafaf2;color:#087344;font-size:11px;font-weight:900;text-decoration:none}.ec-empty{grid-column:1/-1;padding:45px;text-align:center;border:1px dashed var(--line);border-radius:20px;color:var(--muted)}
@media(max-width:760px){.ec-hero{padding:19px}.ec-hero h1{font-size:23px}.ec-stats{grid-template-columns:repeat(2,1fr)}.ec-toolbar{flex-direction:column}.ec-tabs{overflow:auto}.ec-tab{flex:1;white-space:nowrap}.ec-search{width:100%;font-size:16px}.ec-grid{grid-template-columns:1fr}.ec-card{padding:15px}.ec-badges{justify-content:flex-start;margin-top:8px}.ec-top{flex-direction:column}}
</style>
<div class="ec">
    <section class="ec-hero">
        <small>TEK EKRANDA PORTFÖY VE MÜŞTERİ HAFIZASI</small>
        <h1>Emlak CRM</h1>
        <p>Satıcıları, yatırımcıları ve portföy hazırlığını gerçek WhatsApp kayıtlarından takip et.</p>
    </section>

    <div class="ec-stats">
        <div class="ec-stat"><b>{{ $this->stats['portfolios'] }}</b><span>Portföy</span></div>
        <div class="ec-stat"><b>{{ $this->stats['sellers'] }}</b><span>Satıcı</span></div>
        <div class="ec-stat"><b>{{ $this->stats['investors'] }}</b><span>Yatırımcı</span></div>
        <div class="ec-stat"><b>{{ $this->stats['ready'] }}</b><span>Sunuma hazır</span></div>
    </div>

    <div class="ec-toolbar">
        <div class="ec-tabs">
            <button class="ec-tab {{ $tab === 'region' ? 'active' : '' }}" wire:click="setTab('region')">Bölge Ara</button>
            <button class="ec-tab {{ $tab === 'portfolios' ? 'active' : '' }}" wire:click="setTab('portfolios')">Portföyler</button>
            <button class="ec-tab {{ $tab === 'sellers' ? 'active' : '' }}" wire:click="setTab('sellers')">Satıcılar</button>
            <button class="ec-tab {{ $tab === 'investors' ? 'active' : '' }}" wire:click="setTab('investors')">Yatırımcılar</button>
        </div>
        <input
            class="ec-search"
            wire:model.live.debounce.250ms="search"
            placeholder="{{ $tab === 'region' ? 'İl, ilçe veya mahalle yaz…' : 'İsim, telefon, konum veya not ara…' }}"
        >
    </div>

    @if($tab === 'region')
        @php
            $regionRecords = $this->records;
            $portfolioCount = $regionRecords->where('is_investor', false)->count();
            $investorCount = $regionRecords->where('is_investor', true)->count();
        @endphp
        <div class="ec-region-help">
            <b>WhatsApp aramasının daha kullanışlı hali:</b>
            Kayseri, Ankara, İncesu veya Garipçe gibi bir yer yaz. O bölgeyle ilgili geçmişte yazan
            <strong>satıcı/portföyleri ve yatırımcıları aynı ekranda</strong> gör.
            @if(trim($search) !== '')
                <div class="ec-region-counts">
                    <span>{{ $portfolioCount }} portföy</span>
                    <span>{{ $investorCount }} yatırımcı</span>
                    <span>{{ $regionRecords->count() }} toplam kayıt</span>
                </div>
            @endif
        </div>
    @endif

    <div class="ec-grid">
        @forelse($this->records as $record)
            <article class="ec-card" wire:key="{{ $tab }}-{{ $record['id'] }}">
                <div class="ec-top">
                    <div>
                        <div class="ec-name">{{ $record['name'] }}</div>
                        <div class="ec-title">{{ $record['title'] }}</div>
                    </div>
                    <div class="ec-badges">
                        @if($tab === 'region')
                            <span class="ec-role">{{ $record['role_label'] }}</span>
                        @endif
                        <span class="ec-status {{ $record['status_tone'] }}">{{ $record['status_label'] }}</span>
                    </div>
                </div>

                <div class="ec-data">
                    <div><small>KONUM</small><b>{{ $record['location'] }}</b></div>
                    <div><small>{{ $record['price_label'] }}</small><b>{{ $record['price'] ? number_format($record['price'], 0, ',', '.').' TL' : 'Belirtilmedi' }}</b></div>
                    <div><small>{{ $record['is_investor'] ? 'KRİTER TAMLIĞI' : 'DOSYA TAMLIĞI' }}</small><b>%{{ $record['score'] }}</b></div>
                    <div><small>{{ $record['is_investor'] ? 'SON GÜNCELLEME' : 'YATIRIMCI EŞLEŞMESİ' }}</small><b>{{ $record['is_investor'] ? $record['updated_at'] : $record['match_count'].' aday' }}</b></div>
                </div>

                <div class="ec-summary">
                    <small>KONUŞMA ÖZETİ</small>
                    <p>{{ $record['summary'] }}</p>
                </div>

                <div class="ec-action">
                    <small>SIRADAKİ AKSİYON</small>
                    <p>{{ $record['next_action'] }}</p>
                </div>

                @if($record['notes'])
                    <div class="ec-note">Son not: {{ $record['notes'] }}</div>
                @endif

                <div class="ec-foot">
                    <span>{{ $record['phone'] ?: 'Telefon yok' }} · {{ $record['updated_at'] }}</span>
                    @if($record['customer_url'])
                        <a class="ec-open" href="{{ $record['customer_url'] }}">Müşteriyi aç</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="ec-empty">
                @if($tab === 'region' && trim($search) === '')
                    Aramak istediğin ili, ilçeyi veya mahalleyi yaz. Örnek: Kayseri
                @elseif($tab === 'region')
                    “{{ $search }}” için kayıt bulunamadı.
                @else
                    Bu bölümde henüz kayıt bulunmuyor.
                @endif
            </div>
        @endforelse
    </div>
</div>
</x-filament-panels::page>
