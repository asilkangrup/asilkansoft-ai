<x-filament-panels::page>
<style>
.ep{--ink:#122018;--muted:#728078;--line:#e4ebe7;max-width:1180px;margin:auto}.ep-hero{padding:24px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f4f8ff);margin-bottom:14px}.ep-hero small{color:#315b9c;font-weight:900}.ep-hero h1{font-size:28px;font-weight:900;color:var(--ink);margin:6px 0}.ep-hero p{margin:0;color:var(--muted)}
.ep-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.ep-stat{padding:15px;border:1px solid var(--line);border-radius:17px;background:#fff}.ep-stat b{display:block;font-size:24px;color:var(--ink)}.ep-stat span{font-size:11px;color:var(--muted)}
.ep-toolbar{display:flex;justify-content:space-between;gap:10px;margin-bottom:14px}.ep-filters{display:flex;gap:6px;padding:5px;border:1px solid var(--line);border-radius:15px;background:#fff;overflow:auto}.ep-filter{padding:9px 12px;border:0;border-radius:10px;background:transparent;color:var(--muted);font-size:11px;font-weight:850;white-space:nowrap;cursor:pointer}.ep-filter.active{background:var(--ink);color:#fff}.ep-search{width:min(330px,100%);height:44px;border:1px solid var(--line);border-radius:13px;padding:0 13px;background:#fff}
.ep-grid{display:grid;gap:12px}.ep-card{padding:18px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 9px 26px rgba(15,35,25,.035)}.ep-head{display:flex;justify-content:space-between;gap:12px}.ep-property{font-size:17px;font-weight:900;color:var(--ink)}.ep-people{margin-top:4px;font-size:12px;color:var(--muted)}.ep-status{height:29px;padding:0 10px;display:inline-flex;align-items:center;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap}.ep-status.green{background:#e9faf2;color:#087344}.ep-status.blue{background:#eef4ff;color:#315b9c}.ep-status.purple{background:#f3edff;color:#6e42a5}.ep-status.gray{background:#f0f2f1;color:#66706a}.ep-status.orange{background:#fff4dd;color:#875900}
.ep-main{display:grid;grid-template-columns:220px 1fr;gap:12px;margin-top:13px}.ep-amount{padding:14px;border-radius:14px;background:#f7faf8}.ep-amount small,.ep-next small{display:block;font-size:10px;font-weight:900;color:var(--muted)}.ep-amount b{display:block;margin-top:6px;font-size:19px;color:var(--ink)}.ep-next{padding:14px;border-left:3px solid #22c77a;border-radius:12px;background:#f4fbf7}.ep-next p{margin:5px 0 0;font-size:12px;line-height:1.5;color:#405149}
.ep-history{margin-top:12px;border-top:1px solid var(--line);padding-top:10px}.ep-event{display:grid;grid-template-columns:130px 1fr auto;gap:9px;align-items:center;padding:8px 0;border-bottom:1px dashed #edf1ef;font-size:11px}.ep-event:last-child{border:0}.ep-event time{color:var(--muted)}.ep-event b{color:#3f5047}.ep-event span{color:#315b9c;font-weight:850}.ep-links{display:flex;gap:7px;margin-top:10px}.ep-link{padding:8px 11px;border-radius:10px;background:#eef4ff;color:#315b9c;text-decoration:none;font-size:10px;font-weight:900}.ep-empty{padding:45px;text-align:center;border:1px dashed var(--line);border-radius:20px;color:var(--muted)}
@media(max-width:760px){.ep-hero{padding:19px}.ep-hero h1{font-size:23px}.ep-stats{grid-template-columns:repeat(2,1fr)}.ep-toolbar{flex-direction:column}.ep-search{width:100%;font-size:16px}.ep-main{grid-template-columns:1fr}.ep-card{padding:15px}.ep-event{grid-template-columns:1fr auto}.ep-event time{grid-column:1/-1}.ep-head{align-items:flex-start}}
</style>
<div class="ep">
    <section class="ep-hero">
        <small>GERÇEK TEKLİF VE KARŞI TEKLİF HAFIZASI</small>
        <h1>Teklifler ve Pazarlıklar</h1>
        <p>Her portföyde rakamın kimden geldiğini, sıranın kimde olduğunu ve geçmiş görüşmeleri takip et.</p>
    </section>

    <div class="ep-stats">
        <div class="ep-stat"><b>{{ $this->stats['active'] }}</b><span>Aktif pazarlık</span></div>
        <div class="ep-stat"><b>{{ $this->stats['waiting_seller'] }}</b><span>Satıcı sırası</span></div>
        <div class="ep-stat"><b>{{ $this->stats['waiting_investor'] }}</b><span>Yatırımcı sırası</span></div>
        <div class="ep-stat"><b>{{ $this->stats['completed'] }}</b><span>Anlaşma sağlandı</span></div>
    </div>

    <div class="ep-toolbar">
        <div class="ep-filters">
            @foreach(['active'=>'Aktif','waiting_seller'=>'Satıcıda','waiting_investor'=>'Yatırımcıda','completed'=>'Anlaşmalar','all'=>'Tümü'] as $key => $label)
                <button class="ep-filter {{ $filter === $key ? 'active' : '' }}" wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>
        <input class="ep-search" wire:model.live.debounce.300ms="search" placeholder="Portföy, satıcı veya yatırımcı ara…">
    </div>

    <div class="ep-grid">
        @forelse($this->deals as $deal)
            <article class="ep-card" wire:key="{{ $deal['key'] }}">
                <div class="ep-head">
                    <div>
                        <div class="ep-property">{{ $deal['property'] }}</div>
                        <div class="ep-people">{{ $deal['seller_name'] }} ↔ {{ $deal['investor_name'] }}</div>
                    </div>
                    <span class="ep-status {{ $deal['tone'] }}">{{ $deal['stage_label'] }}</span>
                </div>

                <div class="ep-main">
                    <div class="ep-amount">
                        <small>SON RAKAM</small>
                        <b>{{ $deal['amount'] ? number_format($deal['amount'], 0, ',', '.').' TL' : 'Henüz yok' }}</b>
                    </div>
                    <div class="ep-next">
                        <small>SIRADAKİ AKSİYON</small>
                        <p>{{ $deal['next_action'] }}</p>
                    </div>
                </div>

                <div class="ep-history">
                    @foreach($deal['timeline'] as $event)
                        <div class="ep-event">
                            <time>{{ $event['date'] }}</time>
                            <b>{{ $event['title'] }} — {{ $event['outcome'] }}</b>
                            <span>{{ $event['amount'] ? number_format($event['amount'], 0, ',', '.').' TL' : '' }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="ep-links">
                    @if($deal['seller_url'])<a class="ep-link" href="{{ $deal['seller_url'] }}">Satıcı kaydı</a>@endif
                    @if($deal['investor_url'])<a class="ep-link" href="{{ $deal['investor_url'] }}">Yatırımcı kaydı</a>@endif
                </div>
            </article>
        @empty
            <div class="ep-empty">Bu filtrede henüz teklif veya pazarlık kaydı bulunmuyor.</div>
        @endforelse
    </div>
</div>
</x-filament-panels::page>
