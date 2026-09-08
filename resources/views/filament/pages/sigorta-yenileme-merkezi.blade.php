<x-filament-panels::page>
<style>
.ren-page{display:flex;flex-direction:column;gap:18px}.ren-hero{border-radius:24px;padding:24px;background:radial-gradient(circle at 95% 5%,rgba(244,63,94,.15),transparent 28%),radial-gradient(circle at 0 0,rgba(37,99,235,.2),transparent 30%),linear-gradient(145deg,#07111f,#10182a);color:#f8fafc;border:1px solid rgba(148,163,184,.14)}.ren-hero h1{margin:8px 0 8px;font-size:clamp(27px,4vw,42px);letter-spacing:-.04em}.ren-hero p{margin:0;max-width:850px;color:#cbd5e1;font-size:14px;line-height:1.65}.ren-eyebrow{font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#fda4af}.ren-note{margin-top:16px;display:inline-block;padding:9px 11px;border-radius:12px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.2);color:#fde68a;font-size:11px;font-weight:800}.ren-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.ren-kpi,.ren-panel{background:var(--fi-color-white,#fff);border:1px solid rgba(148,163,184,.18);border-radius:19px;box-shadow:0 10px 30px rgba(15,23,42,.05)}.dark .ren-kpi,.dark .ren-panel{background:#111827;border-color:#263348}.ren-kpi{padding:18px}.ren-kpi small{display:block;color:#64748b;font-size:12px;font-weight:850}.dark .ren-kpi small{color:#94a3b8}.ren-kpi strong{display:block;margin-top:7px;color:#0f172a;font-size:28px;font-weight:950}.dark .ren-kpi strong{color:#f8fafc}.ren-grid{display:grid;grid-template-columns:350px 1fr;gap:16px;align-items:start}.ren-head{padding:18px 20px;border-bottom:1px solid rgba(148,163,184,.14)}.ren-head h2{margin:0;color:#0f172a;font-size:17px;font-weight:950}.dark .ren-head h2{color:#f8fafc}.ren-head p{margin:5px 0 0;color:#64748b;font-size:12px;line-height:1.5}.dark .ren-head p{color:#94a3b8}.ren-form{padding:18px;display:grid;gap:11px}.ren-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:850;color:#475569}.dark .ren-field label{color:#cbd5e1}.ren-input{width:100%;padding:10px 11px;border-radius:11px;border:1px solid #dbe3ee;background:#fff;color:#0f172a;font-size:13px;outline:none}.dark .ren-input{background:#0b1220;border-color:#334155;color:#f8fafc}.ren-btn{border:0;border-radius:11px;padding:11px 13px;font-size:12px;font-weight:900;cursor:pointer}.ren-btn.primary{color:white;background:linear-gradient(135deg,#2563eb,#0891b2)}.ren-toolbar{display:flex;gap:8px;flex-wrap:wrap;padding:13px 15px;border-bottom:1px solid rgba(148,163,184,.14)}.ren-toolbar .ren-input{max-width:280px}.ren-filter{display:flex;gap:6px;flex-wrap:wrap}.ren-filter button{padding:7px 9px;border-radius:999px;border:1px solid #dbe3ee;background:#fff;color:#475569;font-size:10px;font-weight:850;cursor:pointer}.dark .ren-filter button{background:#0b1220;border-color:#334155;color:#cbd5e1}.ren-filter button.active{background:#0f172a;color:#fff}.ren-table-wrap{overflow:auto}.ren-table{width:100%;border-collapse:collapse;min-width:980px}.ren-table th{padding:11px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left;color:#64748b;font-size:10px;font-weight:900;text-transform:uppercase}.dark .ren-table th{background:#0b1220;border-color:#253147;color:#94a3b8}.ren-table td{padding:13px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;color:#334155;font-size:12px}.dark .ren-table td{border-color:#1f2a3b;color:#cbd5e1}.ren-main b{display:block;color:#0f172a;font-size:13px}.dark .ren-main b{color:#f8fafc}.ren-main span{display:block;margin-top:3px;color:#64748b;font-size:11px}.dark .ren-main span{color:#94a3b8}.ren-status{display:inline-flex;padding:6px 8px;border-radius:999px;font-size:10px;font-weight:900;background:#e2e8f0;color:#334155}.ren-status.detected{background:#fee2e2;color:#991b1b}.ren-status.assigned{background:#dbeafe;color:#1e40af}.ren-status.recovered{background:#d1fae5;color:#065f46}.ren-actions{display:flex;gap:5px;flex-wrap:wrap}.ren-actions button{padding:7px 8px;border-radius:8px;border:1px solid #dbe3ee;background:#fff;color:#334155;font-size:10px;font-weight:850;cursor:pointer}.dark .ren-actions button{background:#0b1220;border-color:#334155;color:#cbd5e1}.ren-empty{padding:40px 18px;text-align:center;color:#64748b;font-size:13px}@media(max-width:1000px){.ren-grid{grid-template-columns:1fr}.ren-kpis{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.ren-hero{padding:19px}.ren-kpis{grid-template-columns:1fr 1fr}.ren-kpi{padding:14px}.ren-kpi strong{font-size:24px}.ren-toolbar .ren-input{max-width:none;width:100%}}
</style>
@php($summary=$this->summary)
@php($rows=$this->rows)
<div class="ren-page">
<section class="ren-hero">
<div class="ren-eyebrow">WAI Insurance OS</div>
<h1>Poliçe Yenileme & Geri Kazanım Merkezi</h1>
<p>Mevcut poliçe portföyünü takip edin, başka yerde yenilendiği tespit edilen müşterileri ayrı satış kuyruğuna alın ve geri kazanım sonucunu ekip bazında yönetin.</p>
<div class="ren-note">Open Yazılım tarafında poliçe/yenileme dış API’si bulunmadığı teyit edildi. Bu ekran gerçek operasyon altyapısıdır; otomatik tespit kaynağı SBM veya başka yetkili servis bağlandığında aynı veri modeline akacaktır.</div>
</section>
<section class="ren-kpis">
<article class="ren-kpi"><small>Takipte</small><strong>{{ $summary['monitoring'] }}</strong></article>
<article class="ren-kpi"><small>Başka Yerde Yenilendi</small><strong>{{ $summary['detected'] }}</strong></article>
<article class="ren-kpi"><small>Satış Ekibinde</small><strong>{{ $summary['assigned'] }}</strong></article>
<article class="ren-kpi"><small>Geri Kazanıldı</small><strong>{{ $summary['recovered'] }}</strong></article>
</section>
<div class="ren-grid">
<aside class="ren-panel">
<div class="ren-head"><h2>Takip Kaydı Ekle</h2><p>Otomatik veri kaynağı bağlanana kadar mevcut poliçeler panelden veya ileride içe aktarma ile eklenebilir.</p></div>
<form class="ren-form" wire:submit="createRecord">
<div class="ren-field"><label>Müşteri</label><input class="ren-input" wire:model="customerName"></div>
<div class="ren-field"><label>Telefon</label><input class="ren-input" wire:model="phone"></div>
<div class="ren-field"><label>Plaka</label><input class="ren-input" wire:model="plate"></div>
<div class="ren-field"><label>Motor No</label><input class="ren-input" wire:model="motorNumber"></div>
<div class="ren-field"><label>Poliçe No</label><input class="ren-input" wire:model="policyNumber"></div>
<div class="ren-field"><label>Sigorta Şirketi</label><input class="ren-input" wire:model="insurer"></div>
<div class="ren-field"><label>Poliçe Bitiş Tarihi</label><input type="date" class="ren-input" wire:model="expiryDate"></div>
<button class="ren-btn primary" type="submit">Takibe Ekle</button>
</form>
</aside>
<section class="ren-panel">
<div class="ren-head"><h2>Geri Kazanım Kuyruğu</h2><p>Kaçan müşteriyi tespit → satış ekibine aktar → sonucu kapat.</p></div>
<div class="ren-toolbar">
<input class="ren-input" wire:model.live.debounce.350ms="search" placeholder="Müşteri, plaka, poliçe ara...">
<div class="ren-filter">
@foreach(['active'=>'Aktif','external_renewal_detected'=>'Tespit','assigned'=>'Satışta','recovered'=>'Kazanıldı','lost'=>'Kaybedildi','all'=>'Tümü'] as $key=>$label)
<button type="button" class="{{ $filter===$key?'active':'' }}" wire:click="$set('filter','{{ $key }}')">{{ $label }}</button>
@endforeach
</div>
</div>
@if($rows->isEmpty())<div class="ren-empty">Henüz yenileme takip kaydı yok.</div>@else
<div class="ren-table-wrap"><table class="ren-table"><thead><tr><th>Müşteri</th><th>Poliçe / Araç</th><th>Bitiş</th><th>Durum</th><th>Tespit</th><th>Aksiyon</th></tr></thead><tbody>
@foreach($rows as $row)
@php($cls=$row->status==='external_renewal_detected'?'detected':($row->status==='assigned'?'assigned':($row->status==='recovered'?'recovered':'')))
<tr wire:key="renewal-{{ $row->id }}">
<td><div class="ren-main"><b>{{ $row->customer_name ?: 'Müşteri adı yok' }}</b><span>{{ $row->phone ?: 'Telefon yok' }}</span></div></td>
<td><div class="ren-main"><b>{{ $row->policy_number ?: 'Poliçe no yok' }}</b><span>{{ $row->plate ?: 'Plaka yok' }} · {{ $row->insurer ?: 'Şirket yok' }}</span><span>Motor: {{ $row->motor_number ?: '—' }}</span></div></td>
<td>{{ $row->expiry_date?->format('d.m.Y') ?: '—' }}</td>
<td><span class="ren-status {{ $cls }}">{{ $row->statusLabel() }}</span></td>
<td>{{ $row->external_policy_detected_at?->format('d.m.Y H:i') ?: '—' }}</td>
<td><div class="ren-actions">
@if($row->status==='monitoring')<button wire:click="markDetected({{ $row->id }})">Tespit Et</button>@endif
@if($row->status==='external_renewal_detected')<button wire:click="assignToSales({{ $row->id }})">Satışa Aktar</button>@endif
@if(in_array($row->status,['external_renewal_detected','assigned']))<button wire:click="markRecovered({{ $row->id }})">Geri Kazanıldı</button><button wire:click="markLost({{ $row->id }})">Kaybedildi</button>@endif
</div></td>
</tr>
@endforeach
</tbody></table></div>
@endif
</section>
</div>
</div>
</x-filament-panels::page>
