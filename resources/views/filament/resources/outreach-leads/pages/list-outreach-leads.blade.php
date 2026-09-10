<x-filament-panels::page>
    @php
        $selectedSector = $this->getSelectedSector();
        $selectedCity = $this->getSelectedCity();
        $sectors = $this->getSectorSummaries();
        $cities = $selectedSector ? $this->getCityOptions() : collect();
        $mobileLeads = $selectedSector ? $this->getMobileLeads() : null;
        $stats = $this->getMobileStats();
    @endphp

    <div class="wai-mobile-leads">
        <div class="wai-mobile-hero">
            <div>
                <div class="wai-mobile-kicker">WAI LEAD CENTER</div>
                <h2>Bugünkü İşletmeler</h2>
                <p>Yeni ve WhatsApp'a uygun işletmeler üstte. Bir işletmeye dokun, mesajı aç, kaldığın yeri sistem hatırlasın.</p>
            </div>
            <div class="wai-mobile-progress">
                <span>{{ $stats['opened'] }}</span>
                <small>temas açıldı</small>
            </div>
        </div>

        <div class="wai-mobile-stats">
            <div><strong>{{ $stats['ready'] }}</strong><span>Hazır</span></div>
            <div><strong>{{ $stats['verified'] }}</strong><span>WhatsApp</span></div>
            <div><strong>{{ $stats['opened'] }}</strong><span>Gönderildi</span></div>
            <div><strong>{{ $stats['replied'] }}</strong><span>Cevap/WAI</span></div>
        </div>

        @if (! $selectedSector)
            <div class="wai-sector-heading">
                <div>
                    <strong>Sektörler</strong>
                    <span>Bir sektöre dokunarak kayıtları aç</span>
                </div>
                <span>{{ $sectors->count() }} sektör</span>
            </div>
            <div class="wai-sector-grid">
                @foreach ($sectors as $sector)
                    <a class="wai-sector-card" href="{{ request()->fullUrlWithQuery(['sector' => $sector->sector_name, 'ready' => 1, 'page' => null]) }}">
                        <div class="wai-sector-icon">{{ mb_strtoupper(mb_substr($sector->sector_name, 0, 1)) }}</div>
                        <div class="wai-sector-copy">
                            <strong>{{ $sector->sector_name }}</strong>
                            <span>{{ number_format($sector->ready, 0, ',', '.') }} hazır · {{ number_format($sector->verified, 0, ',', '.') }} WhatsApp</span>
                        </div>
                        <b>{{ number_format($sector->total, 0, ',', '.') }}</b>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                @endforeach
            </div>
        @else
            <div class="wai-sector-bar">
                <a href="{{ request()->url() }}" aria-label="Sektörlere dön">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5m6-6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <div><small>Seçili sektör</small><strong>{{ $selectedSector }}</strong></div>
                <span>{{ number_format($mobileLeads->total(), 0, ',', '.') }} kayıt</span>
            </div>

        <form class="wai-mobile-toolbar" method="get">
            <input type="hidden" name="sector" value="{{ $selectedSector }}">
            <div class="wai-mobile-search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input name="q" value="{{ request('q') }}" type="search" placeholder="İşletme veya numara ara">
            </div>
            <button type="submit" name="ready" value="{{ request()->boolean('ready', true) ? '0' : '1' }}" class="wai-mobile-filter {{ request()->boolean('ready', true) ? 'is-active' : '' }}">
                {{ request()->boolean('ready', true) ? 'Sıradakiler' : 'Tümü' }}
            </button>
            <select name="city" class="wai-city-select" onchange="this.form.submit()" aria-label="İle göre filtrele">
                <option value="">Tüm iller</option>
                @foreach ($cities as $cityValue => $cityLabel)
                    <option value="{{ $cityValue }}" @selected($selectedCity === $cityValue)>{{ $cityLabel }}</option>
                @endforeach
            </select>
        </form>

        <div id="waiLeadCards" class="wai-mobile-card-list">
            @forelse ($mobileLeads as $lead)
                @php
                    $statusText = match ($lead->status) {
                        'opened' => 'İlk temas açıldı',
                        'replied' => 'Cevap verdi',
                        'ai_active' => 'WAI aktif',
                        default => 'Hazır',
                    };
                    $statusClass = match ($lead->status) {
                        'opened' => 'is-opened',
                        'replied', 'ai_active' => 'is-success',
                        default => 'is-ready',
                    };
                    $waText = match ($lead->whatsapp_status) {
                        'verified' => 'WhatsApp doğrulandı',
                        'unavailable' => 'WhatsApp yok',
                        default => 'WhatsApp bilinmiyor',
                    };
                    $canOpen = ! $lead->contact_opened_at && $lead->whatsapp_status !== 'unavailable';
                    $leadCity = null;
                    if (preg_match('/Şehir:\s*([^|]+)\s*$/u', (string) $lead->notes, $cityMatch)) {
                        $leadCity = trim($cityMatch[1]);
                    }
                @endphp
                <article class="wai-lead-card" data-ready="{{ $lead->status === 'ready' ? '1' : '0' }}" data-search="{{ mb_strtolower($lead->company_name.' '.$lead->phone_e164.' '.$lead->source) }}">
                    <div class="wai-lead-card-top">
                        <div class="wai-lead-avatar">{{ mb_strtoupper(mb_substr($lead->company_name, 0, 1)) }}</div>
                        <div class="wai-lead-title">
                            <h3>{{ $lead->company_name }}</h3>
                            <a href="tel:{{ $lead->phone_e164 }}">{{ $lead->phone_e164 }}</a>
                        </div>
                        <span class="wai-status {{ $statusClass }}">{{ $statusText }}</span>
                    </div>

                    <div class="wai-lead-chips">
                        <span class="{{ $lead->whatsapp_status === 'verified' ? 'is-green' : ($lead->whatsapp_status === 'unavailable' ? 'is-red' : '') }}">{{ $waText }}</span>
                        @if ($lead->isFreshSource())
                            <span class="is-purple">Yeni kaynak</span>
                        @endif
                        @if ($leadCity && mb_strtolower($leadCity) !== 'belirlenemedi')
                            <span class="is-blue">{{ $leadCity }}</span>
                        @endif
                        @if ($lead->source)
                            <span>{{ $lead->source }}</span>
                        @endif
                    </div>

                    <div class="wai-message-preview">
                        <span>İlk mesaj</span>
                        <p>{{ $lead->first_message_text ?: 'Merhaba kolay gelsin, '.$lead->company_name.' doğru mudur?' }}</p>
                    </div>

                    <div class="wai-lead-meta">
                        <div>
                            <small>Kaynak tarihi</small>
                            <strong>{{ $lead->source_published_at?->format('d.m.Y') ?? 'Belirtilmemiş' }}</strong>
                        </div>
                        <div>
                            <small>Son kontrol</small>
                            <strong>{{ $lead->source_checked_at?->diffForHumans() ?? '—' }}</strong>
                        </div>
                    </div>

                    <div class="wai-card-actions">
                        @if ($canOpen)
                            <a class="wai-whatsapp-button" href="{{ route('outreach-leads.whatsapp', $lead) }}">
                                <span>WhatsApp'ta Aç</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5l8 7-8 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        @else
                            <button class="wai-whatsapp-button is-disabled" disabled>
                                {{ $lead->whatsapp_status === 'unavailable' ? 'WhatsApp Kullanılamıyor' : 'Bu Lead Açıldı' }}
                            </button>
                        @endif
                        @if ($lead->source_url)
                            <a class="wai-source-button" href="{{ $lead->source_url }}" target="_blank" rel="noopener">Kaynağı Gör</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="wai-empty-state">
                    <strong>Henüz lead yok</strong>
                    <span>Toplu Lead Aktar ile listeyi doldurabilirsin.</span>
                </div>
            @endforelse
        </div>

        @if ($mobileLeads->hasPages())
            <div class="wai-mobile-pagination">
                {{ $mobileLeads->onEachSide(1)->links() }}
            </div>
        @endif
        @endif
    </div>

    <div class="wai-desktop-table">
        {{ $this->table }}
    </div>

    <style>
        .wai-mobile-leads{display:none}
        @media(max-width:767px){
            .wai-desktop-table{display:none!important}
            .wai-mobile-leads{display:block;padding-bottom:96px}
            .fi-page-header{margin-bottom:14px}
            .fi-page-header-heading{font-size:24px!important;letter-spacing:-.035em}
            .fi-page-header-actions{display:grid!important;grid-template-columns:1fr 1fr;gap:8px;width:100%}
            .fi-page-header-actions>*{width:100%}
            .fi-page-header-actions button,.fi-page-header-actions a{width:100%;justify-content:center;min-height:46px;border-radius:14px!important}
            .wai-mobile-hero{background:linear-gradient(145deg,#111827 0%,#111827 60%,#1f2937 100%);color:white;border-radius:24px;padding:20px;display:flex;gap:16px;align-items:flex-start;box-shadow:0 18px 45px rgba(15,23,42,.16);position:relative;overflow:hidden}
            .wai-mobile-hero:after{content:"";position:absolute;width:140px;height:140px;border-radius:999px;background:rgba(245,158,11,.18);right:-55px;top:-55px;filter:blur(2px)}
            .wai-mobile-kicker{font-size:10px;font-weight:800;letter-spacing:.18em;color:#fbbf24;margin-bottom:8px}
            .wai-mobile-hero h2{font-size:22px;line-height:1.08;font-weight:800;letter-spacing:-.035em;margin:0 0 8px}
            .wai-mobile-hero p{font-size:12px;line-height:1.55;color:#cbd5e1;margin:0;max-width:235px}
            .wai-mobile-progress{margin-left:auto;min-width:68px;height:68px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);border-radius:18px;display:flex;flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(10px);z-index:1}
            .wai-mobile-progress span{font-size:22px;font-weight:800;line-height:1}.wai-mobile-progress small{font-size:8px;color:#cbd5e1;margin-top:5px;text-align:center}
            .wai-mobile-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0 14px}
            .wai-mobile-stats>div{background:white;border:1px solid #e5e7eb;border-radius:16px;padding:12px 8px;text-align:center;box-shadow:0 5px 16px rgba(15,23,42,.04)}
            .dark .wai-mobile-stats>div{background:#111827;border-color:#263244}
            .wai-mobile-stats strong{font-size:18px;display:block;line-height:1.1}.wai-mobile-stats span{font-size:9px;color:#64748b;display:block;margin-top:4px}
            .wai-sector-heading{display:flex;align-items:end;justify-content:space-between;margin:20px 2px 10px}.wai-sector-heading strong{display:block;font-size:17px}.wai-sector-heading span{font-size:10px;color:#64748b}.wai-sector-heading>span{font-weight:700;background:#eef2f7;padding:6px 9px;border-radius:999px}
            .wai-sector-grid{display:flex;flex-direction:column;gap:9px}.wai-sector-card{display:flex;align-items:center;gap:11px;padding:13px;background:white;border:1px solid #e5e7eb;border-radius:18px;text-decoration:none!important;color:inherit!important;box-shadow:0 5px 18px rgba(15,23,42,.045)}.dark .wai-sector-card{background:#111827;border-color:#263244}.wai-sector-icon{width:42px;height:42px;border-radius:13px;background:#111827;color:#fbbf24;display:flex;align-items:center;justify-content:center;font-weight:900;flex:none}.wai-sector-copy{min-width:0;flex:1}.wai-sector-copy strong{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wai-sector-copy span{display:block;font-size:9px;color:#64748b;margin-top:3px}.wai-sector-card>b{font-size:14px}.wai-sector-card>svg{width:16px;color:#94a3b8}
            .wai-sector-bar{display:flex;align-items:center;gap:10px;margin:18px 0 10px;padding:11px 12px;background:white;border:1px solid #e5e7eb;border-radius:17px}.dark .wai-sector-bar{background:#111827;border-color:#263244}.wai-sector-bar>a{width:38px;height:38px;border-radius:12px;background:#111827;color:white;display:flex;align-items:center;justify-content:center}.wai-sector-bar svg{width:19px}.wai-sector-bar>div{min-width:0;flex:1}.wai-sector-bar small{font-size:8px;color:#64748b;display:block}.wai-sector-bar strong{font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wai-sector-bar>span{font-size:9px;font-weight:800;background:#eff6ff;color:#2563eb;padding:6px 8px;border-radius:999px;white-space:nowrap}
            .wai-mobile-pagination{margin-top:16px}.wai-mobile-pagination nav>div:first-child{display:flex!important}.wai-mobile-pagination nav>div:last-child{display:none!important}
            .wai-mobile-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;position:sticky;top:8px;z-index:20;padding:6px 0 10px;background:linear-gradient(to bottom,var(--fi-body-bg,#f9fafb) 72%,transparent)}
            .wai-mobile-search-wrap{height:46px;min-width:0;background:white;border:1px solid #dfe3e8;border-radius:15px;display:flex;align-items:center;padding:0 13px;box-shadow:0 4px 16px rgba(15,23,42,.04)}
            .dark .wai-mobile-search-wrap{background:#111827;border-color:#263244}.wai-mobile-search-wrap svg{width:18px;color:#94a3b8;flex:none}.wai-mobile-search-wrap input{border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;width:100%;font-size:13px;padding-left:9px;color:inherit}
            .wai-mobile-filter{height:46px;border-radius:15px;border:1px solid #dfe3e8;background:white;padding:0 14px;font-size:11px;font-weight:700;white-space:nowrap}.wai-mobile-filter.is-active{background:#111827;color:white;border-color:#111827}
            .wai-city-select{grid-column:1/-1;height:44px;border:1px solid #dfe3e8;border-radius:14px;background:white;padding:0 13px;font-size:12px;font-weight:700;color:inherit;box-shadow:0 4px 16px rgba(15,23,42,.04)}.dark .wai-city-select{background:#111827;border-color:#263244}
            .wai-mobile-card-list{display:flex;flex-direction:column;gap:10px}
            .wai-lead-card{background:white;border:1px solid #e6e9ed;border-radius:21px;padding:15px;box-shadow:0 7px 24px rgba(15,23,42,.055)}
            .dark .wai-lead-card{background:#111827;border-color:#263244}
            .wai-lead-card-top{display:flex;align-items:center;gap:10px}.wai-lead-avatar{width:43px;height:43px;border-radius:14px;background:#111827;color:#fbbf24;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:17px;flex:none}.wai-lead-title{min-width:0;flex:1}.wai-lead-title h3{font-size:15px;font-weight:800;line-height:1.2;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wai-lead-title a{display:inline-block;margin-top:4px;color:#64748b;font-size:12px}
            .wai-status{font-size:9px;font-weight:800;border-radius:999px;padding:6px 8px;background:#eff6ff;color:#2563eb;white-space:nowrap}.wai-status.is-opened{background:#fff7ed;color:#ea580c}.wai-status.is-success{background:#ecfdf5;color:#059669}
            .wai-lead-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:12px}.wai-lead-chips span{font-size:9px;font-weight:700;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#475569}.wai-lead-chips .is-green{background:#ecfdf5;color:#047857}.wai-lead-chips .is-red{background:#fef2f2;color:#b91c1c}.wai-lead-chips .is-purple{background:#f5f3ff;color:#7c3aed}.wai-lead-chips .is-blue{background:#eff6ff;color:#2563eb}
            .wai-message-preview{margin-top:12px;background:#f8fafc;border-radius:14px;padding:11px 12px}.dark .wai-message-preview{background:#0b1220}.wai-message-preview span{font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#94a3b8}.wai-message-preview p{font-size:12px;line-height:1.45;margin:4px 0 0;color:#334155}.dark .wai-message-preview p{color:#d1d5db}
            .wai-lead-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:11px}.wai-lead-meta>div{border:1px solid #eef0f3;border-radius:12px;padding:9px}.dark .wai-lead-meta>div{border-color:#263244}.wai-lead-meta small{font-size:8px;color:#94a3b8;display:block}.wai-lead-meta strong{font-size:10px;display:block;margin-top:3px}
            .wai-card-actions{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:12px}.wai-whatsapp-button{min-height:49px;border-radius:15px;background:#16a34a;color:white!important;display:flex;align-items:center;justify-content:center;gap:8px;font-size:13px;font-weight:800;text-decoration:none;box-shadow:0 8px 18px rgba(22,163,74,.2)}.wai-whatsapp-button svg{width:17px}.wai-whatsapp-button.is-disabled{background:#e5e7eb;color:#94a3b8!important;box-shadow:none;border:0;width:100%}.wai-source-button{min-height:49px;padding:0 14px;border-radius:15px;border:1px solid #dfe3e8;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:inherit;text-decoration:none;background:white}.dark .wai-source-button{background:#111827;border-color:#334155}
            .wai-empty-state{border:1px dashed #cbd5e1;border-radius:20px;padding:28px;text-align:center}.wai-empty-state strong,.wai-empty-state span{display:block}.wai-empty-state span{font-size:12px;color:#64748b;margin-top:6px}
        }
    </style>


</x-filament-panels::page>
