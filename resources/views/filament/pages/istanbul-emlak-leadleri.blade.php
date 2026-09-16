<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $leads = $this->leads;
    @endphp

    <div class="ie-wrap">
        <section class="ie-hero">
            <div class="ie-hero-copy">
                <div class="ie-kicker">WAI LEAD CENTER</div>
                <h1>İstanbul Emlak</h1>
                <p>İstanbul'daki emlak ofisleri otomatik olarak burada ayrılır. Yeni İstanbul emlak lead'leri eklendikçe liste kendiliğinden güncellenir.</p>
            </div>
            <div class="ie-total">
                <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong>
                <span>toplam kayıt</span>
            </div>
        </section>

        <section class="ie-stats">
            <div class="ie-stat"><strong>{{ $stats['ready'] }}</strong><span>Hazır</span></div>
            <div class="ie-stat"><strong>{{ $stats['verified'] }}</strong><span>WhatsApp</span></div>
            <div class="ie-stat"><strong>{{ $stats['opened'] }}</strong><span>Gönderildi</span></div>
            <div class="ie-stat"><strong>{{ $stats['replied'] }}</strong><span>Cevap / WAI</span></div>
        </section>

        <section class="ie-filter-card">
            <div class="ie-filter-top">
                <span class="ie-city-pill">İstanbul</span>
                <span class="ie-filter-note">Sabit şehir filtresi</span>
            </div>
            <div class="ie-search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.4-4.4m1.4-5.6a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input wire:model.live.debounce.400ms="search" type="search" placeholder="Emlak ofisi veya telefon ara">
            </div>
        </section>

        <section class="ie-leads">
            @forelse ($leads as $lead)
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
                    $canOpen = ! $lead->contact_opened_at && $lead->whatsapp_status !== 'unavailable';
                @endphp

                <article class="ie-lead-card">
                    <div class="ie-lead-head">
                        <div class="ie-avatar">{{ mb_strtoupper(mb_substr($lead->company_name, 0, 1)) }}</div>
                        <div class="ie-lead-title">
                            <h3>{{ $lead->company_name }}</h3>
                            <a href="tel:{{ $lead->phone_e164 }}">{{ $lead->phone_e164 }}</a>
                        </div>
                        <span class="ie-status {{ $statusClass }}">{{ $statusText }}</span>
                    </div>

                    <div class="ie-chips">
                        <span class="ie-chip city">İstanbul</span>
                        <span class="ie-chip {{ $lead->whatsapp_status === 'verified' ? 'wa' : '' }}">
                            {{ $lead->whatsapp_status === 'verified' ? 'WhatsApp doğrulandı' : 'WhatsApp '.$lead->whatsapp_status }}
                        </span>
                        @if ($lead->source)
                            <span class="ie-chip">{{ $lead->source }}</span>
                        @endif
                    </div>

                    <div class="ie-message">
                        <span>İlk mesaj</span>
                        <p>{{ $lead->first_message_text ?: 'Merhaba kolay gelsin, '.$lead->company_name.' doğru mudur?' }}</p>
                    </div>

                    <div class="ie-actions">
                        @if ($canOpen)
                            <a href="{{ route('outreach-leads.whatsapp', $lead) }}" class="ie-wa-btn">WhatsApp'ta Aç</a>
                        @else
                            <button disabled class="ie-wa-btn is-disabled">{{ $lead->whatsapp_status === 'unavailable' ? 'WhatsApp Kullanılamıyor' : 'Bu Lead Açıldı' }}</button>
                        @endif

                        @if ($lead->source_url)
                            <a href="{{ $lead->source_url }}" target="_blank" rel="noopener" class="ie-source-btn">Kaynak</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ie-empty">
                    <strong>İstanbul emlak kaydı bulunamadı</strong>
                    <span>Yeni İstanbul emlak lead'leri geldikçe otomatik burada görünecek.</span>
                </div>
            @endforelse
        </section>

        @if ($stats['total'] > 500 && $search === '')
            <div class="ie-performance-note">Performans için ilk 500 kayıt gösteriliyor. Arama kutusuyla tüm kayıt havuzunda arama yapabilirsin.</div>
        @endif
    </div>

    <style>
        .ie-wrap{width:100%;max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:14px;padding-bottom:84px}
        .ie-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:22px;border-radius:24px;background:linear-gradient(145deg,#111827 0%,#111827 62%,#1f2937 100%);color:#fff;box-shadow:0 16px 42px rgba(15,23,42,.16);position:relative;overflow:hidden}
        .ie-hero:after{content:"";position:absolute;width:170px;height:170px;border-radius:999px;background:rgba(245,158,11,.16);right:-65px;top:-75px}
        .ie-hero-copy{position:relative;z-index:1;min-width:0;flex:1}
        .ie-kicker{font-size:10px;line-height:1;font-weight:900;letter-spacing:.18em;color:#fbbf24;margin-bottom:9px}
        .ie-hero h1{font-size:25px;line-height:1.08;font-weight:900;letter-spacing:-.035em;margin:0 0 8px}
        .ie-hero p{margin:0;max-width:650px;font-size:13px;line-height:1.55;color:#cbd5e1}
        .ie-total{position:relative;z-index:1;min-width:92px;padding:13px 12px;border-radius:17px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);backdrop-filter:blur(10px);text-align:center;flex:none}
        .ie-total strong{display:block;font-size:25px;line-height:1;font-weight:900}.ie-total span{display:block;margin-top:6px;font-size:9px;color:#cbd5e1}

        .ie-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .ie-stat{min-width:0;background:#fff;border:1px solid #e5e7eb;border-radius:17px;padding:14px 12px;box-shadow:0 5px 16px rgba(15,23,42,.045)}
        .ie-stat strong{display:block;font-size:21px;line-height:1.05;font-weight:900;color:#111827}.ie-stat span{display:block;margin-top:5px;font-size:10px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        .ie-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:19px;padding:13px;box-shadow:0 5px 16px rgba(15,23,42,.04)}
        .ie-filter-top{display:flex;align-items:center;gap:8px;margin-bottom:10px}
        .ie-city-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#2563eb;font-size:10px;font-weight:800}
        .ie-filter-note{font-size:10px;color:#64748b}
        .ie-search-wrap{height:48px;display:flex;align-items:center;gap:10px;border:1px solid #e5e7eb;background:#f8fafc;border-radius:14px;padding:0 13px}
        .ie-search-wrap:focus-within{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.12)}
        .ie-search-wrap svg{width:19px;height:19px;flex:none;color:#94a3b8}
        .ie-search-wrap input{width:100%;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:0!important;font-size:14px!important;color:#111827!important}
        .ie-search-wrap input::placeholder{color:#94a3b8}

        .ie-leads{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .ie-lead-card{min-width:0;background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:15px;box-shadow:0 6px 20px rgba(15,23,42,.045)}
        .ie-lead-head{display:flex;align-items:flex-start;gap:11px;min-width:0}
        .ie-avatar{width:43px;height:43px;border-radius:13px;background:#111827;color:#fbbf24;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:900;flex:none}
        .ie-lead-title{min-width:0;flex:1;padding-top:1px}.ie-lead-title h3{margin:0;font-size:14px;line-height:1.25;font-weight:800;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ie-lead-title a{display:inline-block;margin-top:4px;font-size:12px;color:#64748b;text-decoration:none}
        .ie-status{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:800;white-space:nowrap;background:#eef2f7;color:#475569;flex:none}
        .ie-status.is-ready{background:#eff6ff;color:#2563eb}.ie-status.is-opened{background:#fff7ed;color:#c2410c}.ie-status.is-success{background:#ecfdf5;color:#047857}
        .ie-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}.ie-chip{display:inline-flex;align-items:center;max-width:100%;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:9px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ie-chip.city{background:#eff6ff;color:#2563eb}.ie-chip.wa{background:#ecfdf5;color:#047857}
        .ie-message{margin-top:12px;padding:11px 12px;border-radius:13px;background:#f8fafc}.ie-message span{display:block;font-size:9px;line-height:1;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8}.ie-message p{margin:6px 0 0;font-size:12px;line-height:1.5;color:#334155;word-break:break-word}
        .ie-actions{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin-top:12px}.ie-wa-btn,.ie-source-btn{min-height:43px;border-radius:12px;display:flex;align-items:center;justify-content:center;text-align:center;text-decoration:none!important;font-size:12px;font-weight:800;border:0}.ie-wa-btn{background:#16a34a;color:#fff!important;padding:0 14px}.ie-wa-btn:hover{background:#15803d}.ie-wa-btn.is-disabled{background:#e5e7eb;color:#64748b!important;cursor:not-allowed}.ie-source-btn{border:1px solid #e5e7eb;color:#334155!important;background:#fff;padding:0 14px}
        .ie-empty{grid-column:1/-1;text-align:center;padding:34px 20px;border:1px dashed #cbd5e1;border-radius:18px;background:#fff}.ie-empty strong{display:block;font-size:14px}.ie-empty span{display:block;margin-top:5px;font-size:12px;color:#64748b}
        .ie-performance-note{padding:12px 14px;border-radius:13px;background:#fffbeb;color:#92400e;font-size:12px;line-height:1.45}

        .dark .ie-stat,.dark .ie-filter-card,.dark .ie-lead-card,.dark .ie-empty{background:#111827;border-color:#263244}.dark .ie-stat strong,.dark .ie-lead-title h3,.dark .ie-search-wrap input{color:#f8fafc!important}.dark .ie-search-wrap,.dark .ie-message{background:#0f172a;border-color:#263244}.dark .ie-message p,.dark .ie-source-btn{color:#cbd5e1!important}.dark .ie-source-btn{background:#111827;border-color:#334155}

        @media(max-width:767px){
            .ie-wrap{gap:11px;padding-bottom:90px}
            .ie-hero{padding:17px;border-radius:21px;gap:12px}
            .ie-hero h1{font-size:21px}.ie-hero p{font-size:11px;line-height:1.5}.ie-kicker{font-size:9px}
            .ie-total{min-width:72px;padding:11px 9px;border-radius:15px}.ie-total strong{font-size:21px}.ie-total span{font-size:8px}
            .ie-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ie-stat{padding:12px;border-radius:15px}.ie-stat strong{font-size:19px}.ie-stat span{font-size:9px}
            .ie-filter-card{padding:11px;border-radius:17px}.ie-filter-top{margin-bottom:8px}.ie-search-wrap{height:46px}
            .ie-leads{grid-template-columns:1fr;gap:9px}.ie-lead-card{padding:13px;border-radius:18px}.ie-avatar{width:40px;height:40px}.ie-lead-title h3{font-size:13px}.ie-message p{font-size:11px}.ie-actions{grid-template-columns:minmax(0,1fr) auto}.ie-wa-btn,.ie-source-btn{min-height:42px;font-size:11px}
        }

        @media(max-width:390px){
            .ie-hero{display:block}.ie-total{display:flex;align-items:center;gap:8px;width:max-content;margin-top:13px;text-align:left}.ie-total span{margin-top:0}
            .ie-status{font-size:8px;padding:5px 7px}.ie-actions{grid-template-columns:1fr}.ie-source-btn{width:100%}
        }
    </style>
</x-filament-panels::page>
