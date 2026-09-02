<x-filament-panels::page>
<style>
.ed{--ink:#122018;--muted:#718078;--line:#e4ebe7;max-width:1000px;margin:auto}.ed-back{display:inline-flex;margin-bottom:12px;color:#087344;text-decoration:none;font-size:12px;font-weight:900}.ed-head{padding:21px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f3fcf7)}.ed-role{font-size:10px;font-weight:900;color:#087344}.ed-name{margin:5px 0 2px;font-size:25px;font-weight:900;color:var(--ink)}.ed-contact{font-size:13px;color:var(--muted)}.ed-call{display:inline-flex;margin-top:12px;padding:10px 13px;border-radius:11px;background:#eafaf2;color:#087344;text-decoration:none;font-size:12px;font-weight:900}
.ed-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}.ed-card{padding:17px;border:1px solid var(--line);border-radius:18px;background:#fff}.ed-card h2{margin:0 0 12px;font-size:14px;color:var(--ink)}.ed-data{display:grid;grid-template-columns:1fr 1fr;gap:8px}.ed-data div{padding:10px;border-radius:11px;background:#f7faf8}.ed-data small{display:block;font-size:9px;font-weight:900;color:var(--muted)}.ed-data b{display:block;margin-top:4px;font-size:12px;color:#35463d}.ed-action{padding:12px;border-left:3px solid #22c77a;border-radius:10px;background:#f4fbf7;font-size:12px;line-height:1.5;color:#405149}
.ed-notes{white-space:pre-wrap;font-size:12px;line-height:1.55;color:#4b5a52;max-height:180px;overflow:auto}.ed-noteform{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:10px}.ed-noteform textarea{min-height:70px;padding:11px;border:1px solid var(--line);border-radius:12px;resize:vertical}.ed-noteform button{padding:0 15px;border:0;border-radius:12px;background:var(--ink);color:#fff;font-weight:900}
.ed-timeline{display:grid;gap:8px}.ed-event{padding:11px;border-radius:12px;background:#f7faf8}.ed-event b{font-size:11px;color:#35463d}.ed-event p{margin:4px 0;font-size:11px;line-height:1.45;color:#536159}.ed-event small{font-size:9px;color:var(--muted)}
@media(max-width:720px){.ed-grid{grid-template-columns:1fr}.ed-name{font-size:22px}.ed-card{padding:15px}.ed-noteform{grid-template-columns:1fr}.ed-noteform textarea{font-size:16px}.ed-noteform button{height:44px}.ed-call{width:100%;justify-content:center}}
</style>
<div class="ed">
    <a class="ed-back" href="{{ $this->backUrl() }}">← Emlak CRM’e dön</a>

    <section class="ed-head">
        <div class="ed-role">{{ $this->details['role'] }}</div>
        <div class="ed-name">{{ $this->customer->customer_name ?: 'İsimsiz müşteri' }}</div>
        <div class="ed-contact">{{ $this->customer->whatsapp_number }} {{ $this->customer->customer_email ? ' · '.$this->customer->customer_email : '' }}</div>
        @if($this->customer->whatsapp_number)
            <a class="ed-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $this->customer->whatsapp_number) }}">📞 Müşteriyi ara</a>
        @endif
    </section>

    <div class="ed-grid">
        <section class="ed-card">
            <h2>Gayrimenkul Özeti</h2>
            <div class="ed-data">
                <div><small>KONUM</small><b>{{ $this->details['location'] }}</b></div>
                <div><small>TAŞINMAZ TÜRÜ</small><b>{{ $this->details['property_type'] }}</b></div>
                <div><small>M²</small><b>{{ $this->details['area'] }}</b></div>
                <div><small>{{ $this->details['price_label'] }}</small><b>{{ $this->details['price'] ? number_format($this->details['price'], 0, ',', '.').' TL' : 'Belirtilmedi' }}</b></div>
                <div><small>DOSYA TAMLIĞI</small><b>%{{ $this->details['completeness'] }}</b></div>
                <div><small>GÜVEN PUANI</small><b>%{{ $this->details['confidence'] }}</b></div>
            </div>
        </section>

        <section class="ed-card">
            <h2>Sıradaki Aksiyon</h2>
            <div class="ed-action">{{ $this->details['next_action'] }}</div>
        </section>

        <section class="ed-card">
            <h2>CRM Notları</h2>
            <div class="ed-notes">{{ $this->customer->notes ?: 'Henüz not eklenmedi.' }}</div>
            <div class="ed-noteform">
                <textarea wire:model="note" placeholder="Yeni notunu yaz…"></textarea>
                <button wire:click="saveNote">Notu kaydet</button>
            </div>
        </section>

        <section class="ed-card">
            <h2>Müşteri Geçmişi</h2>
            <div class="ed-timeline">
                @forelse($this->activities as $activity)
                    <div class="ed-event">
                        <b>{{ $activity->title }}</b>
                        @if($activity->description)<p>{{ $activity->description }}</p>@endif
                        <small>{{ $activity->created_at?->format('d.m.Y H:i') }} · {{ $activity->actorName() }}</small>
                    </div>
                @empty
                    <div class="ed-event"><small>Henüz CRM aktivitesi yok.</small></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
</x-filament-panels::page>
