<x-filament-panels::page>
    <style>
        .re-match-shell{display:grid;grid-template-columns:minmax(260px,340px) minmax(0,1fr);gap:18px}
        .re-panel,.re-card{border:1px solid rgb(229 231 235);border-radius:18px;background:#fff;box-shadow:0 8px 28px rgba(15,23,42,.05)}
        .dark .re-panel,.dark .re-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}
        .re-panel{padding:16px}.re-card{padding:18px}.re-muted{color:rgb(100 116 139);font-size:13px}.dark .re-muted{color:rgb(148 163 184)}
        .re-title{font-size:18px;font-weight:800;color:rgb(15 23 42)}.dark .re-title{color:#fff}
        .re-seller{width:100%;text-align:left;padding:12px;border-radius:14px;border:1px solid transparent;margin-top:8px;transition:.15s}
        .re-seller:hover,.re-seller.active{background:rgb(248 250 252);border-color:rgb(226 232 240)}.dark .re-seller:hover,.dark .re-seller.active{background:rgb(31 41 55);border-color:rgb(75 85 99)}
        .re-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px}
        .re-input,.re-select{width:100%;border:1px solid rgb(203 213 225);border-radius:12px;padding:9px 11px;background:transparent;font-size:13px}
        .dark .re-input,.dark .re-select{border-color:rgb(75 85 99);color:#fff}
        .re-btn{border-radius:12px;padding:9px 13px;font-size:13px;font-weight:700;background:rgb(15 23 42);color:#fff}.dark .re-btn{background:#fff;color:rgb(15 23 42)}
        .re-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.re-candidate{position:relative}
        .re-score{font-size:28px;font-weight:900;letter-spacing:-1px}.re-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800;background:rgb(241 245 249);color:rgb(51 65 85)}
        .dark .re-badge{background:rgb(55 65 81);color:rgb(226 232 240)}.re-badge.ready{background:rgb(220 252 231);color:rgb(22 101 52)}.dark .re-badge.ready{background:rgba(22,101,52,.25);color:rgb(134 239 172)}
        .re-kv{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin:13px 0}.re-kv div{font-size:12px;padding:8px 9px;border-radius:10px;background:rgb(248 250 252)}.dark .re-kv div{background:rgb(31 41 55)}
        .re-list{margin:10px 0 0 18px;font-size:12px;color:rgb(71 85 105)}.dark .re-list{color:rgb(203 213 225)}
        .re-action{margin-top:13px;padding:10px 12px;border-radius:12px;background:rgb(248 250 252);font-size:12px;font-weight:650}.dark .re-action{background:rgb(31 41 55)}
        .re-link{font-size:12px;font-weight:800;text-decoration:underline}.re-guard{margin-top:14px;padding:11px 13px;border-radius:12px;background:rgb(254 252 232);color:rgb(113 63 18);font-size:12px}.dark .re-guard{background:rgba(113,63,18,.24);color:rgb(253 224 71)}
        @media(max-width:980px){.re-match-shell{grid-template-columns:1fr}.re-grid{grid-template-columns:1fr}}
    </style>

    <div class="re-match-shell">
        <aside class="re-panel">
            <div class="re-title">Satıcı dosyaları</div>
            <div class="re-muted">Yalnız izole Emlak AI portföyü</div>
            <div style="margin-top:12px">
                <input class="re-input" wire:model.live.debounce.350ms="search" placeholder="Satıcı, telefon, konum veya tür ara">
            </div>

            <div style="margin-top:10px;max-height:70vh;overflow:auto">
                @forelse($this->sellers as $seller)
                    @php $d=is_array($seller->data)?$seller->data:[]; @endphp
                    <button type="button" wire:click="selectSeller({{ $seller->id }})" class="re-seller {{ $selectedSellerProfileId===$seller->id?'active':'' }}">
                        <div style="font-weight:800">{{ $seller->conversation?->customer_name ?: 'İsimsiz satıcı' }}</div>
                        <div class="re-muted">{{ implode(' · ', array_filter([$d['city']??null,$d['district']??null,$d['property_type']??null])) ?: 'Taşınmaz bilgisi eksik' }}</div>
                        <div class="re-muted">{{ count(is_array($d['opportunity_matches']??null)?$d['opportunity_matches']:[]) }} kayıtlı eşleşme</div>
                    </button>
                @empty
                    <div class="re-muted" style="padding:18px 4px">Satıcı dosyası bulunamadı.</div>
                @endforelse
            </div>
        </aside>

        <main>
            @if($this->selectedSeller)
                @php $short=$this->shortlist; $candidates=$short['candidates']??[]; @endphp
                <section class="re-panel" style="margin-bottom:14px">
                    <div class="re-toolbar">
                        <div>
                            <div class="re-title">{{ $this->selectedSeller->conversation?->customer_name ?: 'Satıcı' }}</div>
                            <div class="re-muted">{{ $short['property_label'] ?? 'Taşınmaz bilgisi eksik' }} · {{ $short['candidate_count'] ?? 0 }} yatırımcı eşleşmesi · {{ $short['ready_to_call_count'] ?? 0 }} aramaya hazır</div>
                        </div>
                        <button type="button" wire:click="refreshMatches" class="re-btn">Eşleşmeleri yeniden hesapla</button>
                    </div>

                    <div style="max-width:260px">
                        <select class="re-select" wire:model.live="readinessFilter">
                            <option value="all">Tüm adaylar</option>
                            <option value="ready_to_call">Aramaya hazır</option>
                            <option value="criteria_incomplete">Kriter teyidi gerekli</option>
                            <option value="review_match">Eşleşmeyi incele</option>
                        </select>
                    </div>
                    <div class="re-guard">Bir satıcı, kriterleri uyan birden fazla yatırımcıyla aynı anda eşleşebilir. Sistem kişi risk etiketi üretmez; yalnız eksik taşınmaz ve kriter bilgilerini kontrol maddesi olarak gösterir. Otomatik WhatsApp mesajı veya takip göndermez; satıcının gizli taban fiyatı ve aciliyet gerekçesi yatırımcı eşleşmesine aktarılmaz.</div>
                </section>

                <div class="re-grid">
                    @forelse($candidates as $candidate)
                        @php $criteria=$candidate['criteria']??[]; $ready=($candidate['readiness']??'')==='ready_to_call'; @endphp
                        <article class="re-card re-candidate">
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                <div>
                                    <div style="font-weight:900;font-size:16px">{{ $candidate['name'] }}</div>
                                    <div class="re-muted">{{ $candidate['phone'] ?: 'Telefon yok' }}</div>
                                </div>
                                <div style="text-align:right">
                                    <div class="re-score">{{ $candidate['match_score'] }}</div>
                                    <div class="re-muted">eşleşme skoru</div>
                                </div>
                            </div>

                            <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:10px">
                                <span class="re-badge {{ $ready?'ready':'' }}">{{ $candidate['readiness_label'] }}</span>
                                <span class="re-badge">Mandat {{ $candidate['mandate_strength'] }}/100</span>
                                <span class="re-badge">{{ strtoupper($candidate['grade']) }}</span>
                            </div>

                            <div class="re-kv">
                                <div><strong>Bütçe</strong><br>{{ isset($criteria['budget_max']) && $criteria['budget_max'] ? number_format($criteria['budget_max'],0,',','.') .' TL' : 'Net değil' }}</div>
                                <div><strong>Finansman</strong><br>{{ $criteria['financing'] ?: 'Net değil' }}</div>
                                <div><strong>Hedef bölge</strong><br>{{ implode(' / ',array_filter([$criteria['city']??null,$criteria['district']??null])) ?: 'Net değil' }}</div>
                                <div><strong>Tür</strong><br>{{ $criteria['property_type'] ?: 'Net değil' }}</div>
                                <div><strong>Yatırım amacı</strong><br>{{ $criteria['investment_goal'] ?: 'Net değil' }}</div>
                                <div><strong>İşlem süresi</strong><br>{{ $criteria['timeline'] ?: 'Net değil' }}</div>
                            </div>

                            @if(!empty($candidate['reasons']))
                                <div style="font-size:12px;font-weight:800">Neden eşleşiyor?</div>
                                <ul class="re-list">@foreach($candidate['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                            @endif

                            @if(!empty($candidate['risks']))
                                <div style="font-size:12px;font-weight:800;margin-top:10px">Kontrol edilecekler</div>
                                <ul class="re-list">@foreach($candidate['risks'] as $risk)<li>{{ $risk }}</li>@endforeach</ul>
                            @endif

                            @if(!empty($candidate['recommended_next_question']) && !$candidate['mandate_core_ready'])
                                <div class="re-action"><strong>Eksik kriter:</strong> {{ $candidate['recommended_next_question'] }}</div>
                            @endif

                            <div class="re-action">{{ $candidate['recommended_operator_action'] }}</div>

                            @if($candidate['crm_url'])
                                <div style="margin-top:12px"><a class="re-link" href="{{ $candidate['crm_url'] }}">Yatırımcı CRM dosyasını aç →</a></div>
                            @endif
                        </article>
                    @empty
                        <section class="re-card" style="grid-column:1/-1">
                            <div class="re-title">Uygun aday görünmüyor</div>
                            <div class="re-muted" style="margin-top:6px">Filtreyi değiştirin veya “Eşleşmeleri yeniden hesapla” ile mevcut yatırımcı kriterlerini tekrar değerlendirin. Bu işlem otomatik mesaj göndermez.</div>
                        </section>
                    @endforelse
                </div>
            @else
                <section class="re-card"><div class="re-title">Satıcı seçin</div><div class="re-muted">Yatırımcı eşleşmelerini görmek için soldan bir satıcı dosyası seçin.</div></section>
            @endif
        </main>
    </div>
</x-filament-panels::page>
