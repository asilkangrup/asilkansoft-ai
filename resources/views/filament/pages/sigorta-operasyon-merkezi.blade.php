<x-filament-panels::page>
<style>
.ins-page{display:flex;flex-direction:column;gap:20px}.ins-hero{border-radius:26px;padding:26px;background:radial-gradient(circle at 90% 10%,rgba(34,211,238,.18),transparent 28%),radial-gradient(circle at 0 0,rgba(59,130,246,.22),transparent 30%),linear-gradient(145deg,#07111f,#0d1b2f);color:#f8fafc;border:1px solid rgba(148,163,184,.14);box-shadow:0 24px 70px rgba(15,23,42,.16)}.ins-hero-top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.ins-eyebrow{font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#7dd3fc}.ins-hero h1{font-size:clamp(28px,4vw,44px);line-height:1.05;margin:8px 0 10px;letter-spacing:-.04em}.ins-hero p{max-width:800px;color:#cbd5e1;font-size:14px;line-height:1.65;margin:0}.ins-connection{min-width:250px;padding:14px 16px;border-radius:16px;background:rgba(15,23,42,.52);border:1px solid rgba(148,163,184,.18)}.ins-connection b{display:block;font-size:13px}.ins-connection span{display:block;margin-top:5px;font-size:12px;color:#94a3b8}.ins-pill{display:inline-flex;align-items:center;gap:7px;margin-top:10px;padding:7px 10px;border-radius:999px;font-size:11px;font-weight:900}.ins-pill.ok{background:rgba(16,185,129,.15);color:#a7f3d0}.ins-pill.wait{background:rgba(245,158,11,.14);color:#fde68a}.ins-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ins-kpi{padding:18px;border-radius:18px;background:var(--fi-color-white,#fff);border:1px solid rgba(148,163,184,.18);box-shadow:0 10px 30px rgba(15,23,42,.05)}.dark .ins-kpi{background:#111827;border-color:#243244}.ins-kpi small{display:block;font-size:12px;font-weight:800;color:#64748b}.dark .ins-kpi small{color:#94a3b8}.ins-kpi strong{display:block;margin-top:7px;font-size:28px;line-height:1;font-weight:950;color:#0f172a}.dark .ins-kpi strong{color:#f8fafc}.ins-grid{display:grid;grid-template-columns:380px 1fr;gap:16px;align-items:start}.ins-panel{border-radius:20px;background:var(--fi-color-white,#fff);border:1px solid rgba(148,163,184,.18);box-shadow:0 10px 32px rgba(15,23,42,.05);overflow:hidden}.dark .ins-panel{background:#111827;border-color:#243244}.ins-panel-head{padding:18px 20px;border-bottom:1px solid rgba(148,163,184,.14)}.ins-panel-head h2{margin:0;font-size:17px;font-weight:950;color:#0f172a}.dark .ins-panel-head h2{color:#f8fafc}.ins-panel-head p{margin:5px 0 0;font-size:12px;line-height:1.5;color:#64748b}.dark .ins-panel-head p{color:#94a3b8}.ins-form{padding:18px;display:grid;gap:12px}.ins-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:850;color:#475569}.dark .ins-field label{color:#cbd5e1}.ins-input,.ins-select{width:100%;border-radius:12px;border:1px solid #dbe3ee;background:#fff;color:#0f172a;padding:11px 12px;font-size:13px;outline:none;transition:.15s}.dark .ins-input,.dark .ins-select{background:#0b1220;border-color:#334155;color:#f8fafc}.ins-input:focus,.ins-select:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.12)}.ins-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ins-btn{border:0;border-radius:12px;padding:12px 14px;font-size:13px;font-weight:900;cursor:pointer;transition:.15s}.ins-btn:hover{transform:translateY(-1px)}.ins-btn.primary{color:white;background:linear-gradient(135deg,#2563eb,#0891b2);box-shadow:0 10px 24px rgba(37,99,235,.2)}.ins-btn.secondary{color:#0f172a;background:#f1f5f9;border:1px solid #dbe3ee}.dark .ins-btn.secondary{color:#e2e8f0;background:#172033;border-color:#334155}.ins-btn.success{color:white;background:linear-gradient(135deg,#059669,#0f766e)}.ins-toolbar{display:flex;flex-wrap:wrap;gap:9px;padding:14px 16px;border-bottom:1px solid rgba(148,163,184,.14)}.ins-toolbar .ins-input{max-width:280px}.ins-filter{display:flex;gap:7px;flex-wrap:wrap}.ins-filter button{border-radius:999px;border:1px solid #dbe3ee;background:#fff;padding:8px 10px;font-size:11px;font-weight:850;color:#475569;cursor:pointer}.dark .ins-filter button{background:#0b1220;border-color:#334155;color:#cbd5e1}.ins-filter button.active{background:#0f172a;color:white;border-color:#0f172a}.dark .ins-filter button.active{background:#e2e8f0;color:#0f172a}.ins-table-wrap{overflow:auto}.ins-table{width:100%;border-collapse:collapse;min-width:980px}.ins-table th{padding:11px 13px;background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left;color:#64748b;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.dark .ins-table th{background:#0b1220;border-color:#243244;color:#94a3b8}.ins-table td{padding:13px;border-bottom:1px solid #eef2f7;vertical-align:top;font-size:12px;color:#334155}.dark .ins-table td{border-color:#1f2a3b;color:#cbd5e1}.ins-case-main b{display:block;font-size:13px;color:#0f172a}.dark .ins-case-main b{color:#f8fafc}.ins-case-main span{display:block;color:#64748b;font-size:11px;margin-top:3px}.dark .ins-case-main span{color:#94a3b8}.ins-status{display:inline-flex;padding:6px 8px;border-radius:999px;font-size:10px;font-weight:900;background:#e2e8f0;color:#334155}.ins-status.quoted{background:#d1fae5;color:#065f46}.ins-status.attn{background:#fee2e2;color:#991b1b}.ins-status.pending{background:#fef3c7;color:#92400e}.ins-price{font-size:15px;font-weight:950;color:#0f172a}.dark .ins-price{color:#f8fafc}.ins-actions{display:flex;flex-wrap:wrap;gap:6px}.ins-actions button{border:1px solid #dbe3ee;background:#fff;color:#334155;border-radius:9px;padding:7px 8px;font-size:10px;font-weight:850;cursor:pointer}.dark .ins-actions button{background:#0b1220;border-color:#334155;color:#cbd5e1}.ins-inline{display:flex;gap:6px;align-items:center}.ins-inline input{width:105px;border:1px solid #dbe3ee;border-radius:9px;padding:7px 8px;font-size:11px}.dark .ins-inline input{background:#0b1220;border-color:#334155;color:#fff}.ins-empty{padding:42px 20px;text-align:center;color:#64748b;font-size:13px}.ins-note{padding:12px 14px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:11px;line-height:1.6}.dark .ins-note{background:rgba(30,64,175,.15);border-color:rgba(96,165,250,.25);color:#bfdbfe}@media(max-width:1050px){.ins-hero-top{flex-direction:column}.ins-connection{width:100%;min-width:0}.ins-kpis{grid-template-columns:repeat(2,1fr)}.ins-grid{grid-template-columns:1fr}}@media(max-width:640px){.ins-hero{padding:20px;border-radius:21px}.ins-kpis{grid-template-columns:1fr 1fr}.ins-kpi{padding:15px}.ins-kpi strong{font-size:24px}.ins-row2{grid-template-columns:1fr}.ins-toolbar{align-items:stretch}.ins-toolbar .ins-input{max-width:none;width:100%}}
</style>

@php
    $summary = $this->summary;
    $open = $this->openStatus;
    $cases = $this->cases;
@endphp

<div class="ins-page">
    <section class="ins-hero">
        <div class="ins-hero-top">
            <div>
                <div class="ins-eyebrow">WAI Insurance OS</div>
                <h1>Sigorta Operasyon Merkezi</h1>
                <p>WhatsApp'tan gelen ruhsat ve müşteri taleplerini tek işlem kuyruğunda yönetin; Open Hızlı Teklif entegrasyonu aktif olduğunda gerçek teklif verilerini aynı ekranda takip edin. Bu ekran demo değil, canlı operasyon altyapısının yönetim merkezidir.</p>
            </div>
            <div class="ins-connection">
                <b>Open Hızlı Teklif bağlantısı</b>
                @if($open['configured'])
                    <span>Acente Kodu ve Token tanımlı.</span>
                    <span class="ins-pill ok">● BAĞLANTI HAZIR</span>
                @else
                    <span>Altyapı hazır. Son aşamada Acente Kodu + Token tanımlanacak.</span>
                    <span class="ins-pill wait">● KİMLİK BİLGİSİ BEKLENİYOR</span>
                @endif
            </div>
        </div>
    </section>

    <section class="ins-kpis">
        <article class="ins-kpi"><small>Aktif İşlem</small><strong>{{ number_format($summary['active'],0,',','.') }}</strong></article>
        <article class="ins-kpi"><small>Teklif Kuyruğu</small><strong>{{ number_format($summary['waiting'],0,',','.') }}</strong></article>
        <article class="ins-kpi"><small>Teklifi Hazır</small><strong>{{ number_format($summary['quoted'],0,',','.') }}</strong></article>
        <article class="ins-kpi"><small>Kontrol Gereken</small><strong>{{ number_format($summary['attention'],0,',','.') }}</strong></article>
    </section>

    <div class="ins-grid">
        <aside class="ins-panel">
            <div class="ins-panel-head">
                <h2>Yeni Sigorta İşlemi</h2>
                <p>WhatsApp otomasyonu bağlandığında bu kayıtlar otomatik oluşacak. Şimdilik panelden gerçek operasyon kayıtları oluşturabilirsiniz.</p>
            </div>
            <form class="ins-form" wire:submit="createOperation">
                <div class="ins-field"><label>Müşteri / Firma</label><input class="ins-input" wire:model="customerName" placeholder="Müşteri adı"></div>
                <div class="ins-row2">
                    <div class="ins-field"><label>Telefon</label><input class="ins-input" wire:model="phone" placeholder="05xx..."></div>
                    <div class="ins-field"><label>Poliçe Türü</label><select class="ins-select" wire:model="policyType"><option>TRAFIK</option><option>KASKO</option><option>TSS</option><option>DASK</option><option>KONUT</option><option>IMM</option></select></div>
                </div>
                <div class="ins-field"><label>Plaka</label><input class="ins-input" wire:model="plate" placeholder="34ABC123"></div>
                <div class="ins-field"><label>Motor No</label><input class="ins-input" wire:model="motorNumber"></div>
                <div class="ins-field"><label>Şasi No</label><input class="ins-input" wire:model="chassisNumber"></div>
                <div class="ins-field"><label>Ruhsat / Belge No</label><input class="ins-input" wire:model="licenseNumber"></div>
                <button class="ins-btn primary" type="submit">İşlemi Oluştur</button>
                <div class="ins-note">Open API kimlik bilgisi henüz tanımlı değilse hiçbir sahte fiyat üretilmez. Kayıt gerçek operasyon kuyruğunda bekler; token bağlandığında aynı kayıt üzerinden senkronize edilir.</div>
            </form>
        </aside>

        <section class="ins-panel">
            <div class="ins-panel-head">
                <h2>Operasyon Kuyruğu</h2>
                <p>Müşteri, araç, teklif ve işlem durumunu tek ekranda takip edin.</p>
            </div>
            <div class="ins-toolbar">
                <input class="ins-input" wire:model.live.debounce.350ms="search" placeholder="Müşteri, telefon, plaka, motor/şasi ara...">
                <div class="ins-filter">
                    @foreach(['active'=>'Aktif','quoted'=>'Teklif Hazır','attention'=>'Kontrol','issued'=>'Poliçelendi','all'=>'Tümü'] as $key=>$label)
                        <button type="button" class="{{ $filter === $key ? 'active' : '' }}" wire:click="$set('filter','{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if($cases->isEmpty())
                <div class="ins-empty">Henüz sigorta işlemi yok. İlk kayıt oluştuğunda operasyon burada başlayacak.</div>
            @else
                <div class="ins-table-wrap">
                    <table class="ins-table">
                        <thead><tr><th>İşlem</th><th>Araç</th><th>Durum</th><th>Open</th><th>En İyi Prim</th><th>Aksiyon</th></tr></thead>
                        <tbody>
                        @foreach($cases as $case)
                            @php
                                $best = $this->bestPremium($case);
                                $statusClass = in_array($case->status,['needs_attention','failed']) ? 'attn' : ($case->status === 'quoted' ? 'quoted' : (in_array($case->status,['waiting_vehicle','ready_for_open','open_pending']) ? 'pending' : ''));
                            @endphp
                            <tr wire:key="insurance-case-{{ $case->id }}">
                                <td><div class="ins-case-main"><b>#{{ $case->id }} · {{ $case->customer_name ?: 'Müşteri adı bekleniyor' }}</b><span>{{ $case->phone ?: 'Telefon yok' }} · {{ $case->policy_type }}</span><span>{{ $case->created_at->format('d.m.Y H:i') }}</span></div></td>
                                <td><div class="ins-case-main"><b>{{ $case->plate ?: 'Plaka bekleniyor' }}</b><span>Motor: {{ $case->motor_number ?: '—' }}</span><span>Şasi: {{ $case->chassis_number ? \Illuminate\Support\Str::limit($case->chassis_number,18) : '—' }}</span></div></td>
                                <td><span class="ins-status {{ $statusClass }}">{{ $case->statusLabel() }}</span>@if($case->integration_error)<div style="margin-top:6px;color:#dc2626;font-size:10px;max-width:220px">{{ \Illuminate\Support\Str::limit($case->integration_error,90) }}</div>@endif</td>
                                <td>
                                    @if($case->open_teklif_id)
                                        <div class="ins-case-main"><b>Teklif #{{ $case->open_teklif_id }}</b><span>{{ $case->integration_status }}</span>@if($case->last_synced_at)<span>{{ $case->last_synced_at->format('d.m H:i') }}</span>@endif</div>
                                    @else
                                        <div class="ins-inline"><input type="number" wire:model="openTeklifIds.{{ $case->id }}" placeholder="Teklif ID"><button type="button" wire:click="saveOpenTeklifId({{ $case->id }})">Bağla</button></div>
                                    @endif
                                </td>
                                <td>@if($best !== null)<div class="ins-price">{{ number_format($best,2,',','.') }} TL</div><div style="font-size:10px;color:#64748b">{{ $case->quotes->first()?->company_name ?: 'Open sonucu' }}</div>@else<span style="color:#94a3b8">Henüz fiyat yok</span>@endif</td>
                                <td>
                                    <div class="ins-actions">
                                        @if($case->open_teklif_id)<button type="button" wire:click="syncOpen({{ $case->id }})">Open Senkronize</button>@endif
                                        @if($case->status === 'quoted')<button type="button" wire:click="moveToPayment({{ $case->id }})">Ödemeye Geç</button>@endif
                                        @if($case->status === 'payment_ready')<button type="button" wire:click="markIssued({{ $case->id }})">Poliçelendi</button>@endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
</x-filament-panels::page>
