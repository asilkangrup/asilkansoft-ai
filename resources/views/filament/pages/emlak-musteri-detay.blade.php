<x-filament-panels::page>
<style>
.ed{--ink:#122018;--muted:#718078;--line:#e4ebe7;width:100%;max-width:1100px;margin:auto}.ed *{box-sizing:border-box}.ed-back{display:inline-flex;margin-bottom:12px;color:#087344;text-decoration:none;font-size:12px;font-weight:900}.ed-head{padding:21px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f3fcf7)}.ed-role{font-size:10px;font-weight:900;color:#087344}.ed-name{margin:5px 0 2px;font-size:25px;font-weight:900;color:var(--ink)}.ed-contact{font-size:13px;color:var(--muted)}.ed-call{display:inline-flex;margin-top:12px;padding:10px 13px;border-radius:11px;background:#eafaf2;color:#087344;text-decoration:none;font-size:12px;font-weight:900}
.ed-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.ed-card{min-width:0;padding:17px;border:1px solid var(--line);border-radius:18px;background:#fff}.ed-wide{grid-column:1/-1}.ed-card h2{margin:0 0 12px;font-size:14px;color:var(--ink)}.ed-data{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ed-data div{min-width:0;padding:10px;border-radius:11px;background:#f7faf8}.ed-data small{display:block;font-size:9px;font-weight:900;color:var(--muted)}.ed-data b{display:block;margin-top:4px;font-size:12px;line-height:1.35;color:#35463d;overflow-wrap:anywhere}.ed-action,.ed-script{padding:12px;border-left:3px solid #22c77a;border-radius:10px;background:#f4fbf7;font-size:12px;line-height:1.55;color:#405149}.ed-script{border-color:#d79a16;background:#fffaf0}
.ed-checks{display:flex;flex-wrap:wrap;gap:7px}.ed-check{padding:8px 10px;border-radius:999px;background:#fff1ed;color:#9a4635;font-size:10px;font-weight:850}.ed-ok{padding:12px;border-radius:11px;background:#f4fbf7;color:#087344;font-size:11px}
.ed-gallery-groups{display:grid;gap:15px}.ed-gallery-group h3{margin:0 0 8px;font-size:11px;color:#536159}.ed-gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.ed-media{display:block;overflow:hidden;border:1px solid var(--line);border-radius:13px;background:#f7faf8;color:#315b9c;text-decoration:none}.ed-media img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}.ed-media-file,.ed-media-empty{display:grid;place-items:center;aspect-ratio:4/3;padding:12px;text-align:center;font-size:11px;font-weight:900}.ed-media-empty{border:1px dashed var(--line);border-radius:13px;color:var(--muted);background:#fafcfb}.ed-media-meta{display:block;padding:7px;font-size:9px;color:var(--muted)}
.ed-chat{display:grid;gap:8px;max-height:460px;overflow:auto}.ed-msg{max-width:86%;padding:10px 12px;border-radius:14px;background:#f2f5f3;color:#405149}.ed-msg.customer{justify-self:start;border-bottom-left-radius:4px}.ed-msg.ai,.ed-msg.human{justify-self:end;background:#eaf8f1;border-bottom-right-radius:4px}.ed-msg p{margin:0;white-space:pre-wrap;font-size:11px;line-height:1.5}.ed-msg small{display:block;margin-top:5px;font-size:8px;color:var(--muted)}
.ed-notes{white-space:pre-wrap;font-size:12px;line-height:1.55;color:#4b5a52;max-height:180px;overflow:auto}.ed-noteform{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:10px}.ed-noteform textarea{min-height:70px;padding:11px;border:1px solid var(--line);border-radius:12px;resize:vertical}.ed-noteform button{padding:0 15px;border:0;border-radius:12px;background:var(--ink);color:#fff;font-weight:900}.ed-timeline{display:grid;gap:8px}.ed-event{padding:11px;border-radius:12px;background:#f7faf8}.ed-event b{font-size:11px;color:#35463d}.ed-event p{margin:4px 0;font-size:11px;line-height:1.45;color:#536159}.ed-event small{font-size:9px;color:var(--muted)}
@media(max-width:720px){.ed-grid{grid-template-columns:minmax(0,1fr)}.ed-wide{grid-column:auto}.ed-name{font-size:22px}.ed-card{padding:14px}.ed-data{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-noteform{grid-template-columns:1fr}.ed-noteform textarea{font-size:16px}.ed-noteform button{height:44px}.ed-call{width:100%;justify-content:center}.ed-msg{max-width:92%}}
@media(max-width:380px){.ed-data{grid-template-columns:1fr}}
</style>
<div class="ed">
    <a class="ed-back" href="{{ $this->backUrl() }}">← Emlak CRM’e dön</a>
    <section class="ed-head">
        <div class="ed-role">{{ $this->details['role'] }} · TELEFON GÖRÜŞME DOSYASI</div>
        <div class="ed-name">{{ $this->customer->customer_name ?: 'İsimsiz müşteri' }}</div>
        <div class="ed-contact">{{ $this->customer->whatsapp_number }} {{ $this->customer->customer_email ? ' · '.$this->customer->customer_email : '' }}</div>
        @if($this->customer->whatsapp_number)<a class="ed-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $this->customer->whatsapp_number) }}">📞 Müşteriyi ara</a>@endif
    </section>

    <div class="ed-grid">
        <section class="ed-card ed-wide"><h2>Gayrimenkul Bilgileri</h2><div class="ed-data">
            @foreach($this->dossier['property'] as $label=>$value)
                <div><small>{{ mb_strtoupper($label) }}</small><b>{{ $value ?: 'Belirtilmedi' }}</b></div>
            @endforeach
        </div></section>

        <section class="ed-card"><h2>Fiyat ve Ticari Fırsat</h2><div class="ed-data">
            @foreach($this->dossier['pricing'] as $label=>$value)
                <div><small>{{ mb_strtoupper($label) }}</small><b>{{ $value ? number_format($value,0,',','.').' TL' : 'Hesaplanmadı' }}</b></div>
            @endforeach
        </div></section>

        <section class="ed-card"><h2>Satıcı ve Dosya Durumu</h2><div class="ed-data">
            @foreach($this->dossier['motivation'] as $label=>$value)
                <div><small>{{ mb_strtoupper($label) }}</small><b>{{ ($value === null || $value === '') ? 'Belirtilmedi' : $value }}</b></div>
            @endforeach
        </div></section>

        <section class="ed-card"><h2>Telefonda Söyle</h2><div class="ed-script">{{ $this->dossier['call_script'] }}</div></section>
        <section class="ed-card"><h2>Sıradaki Aksiyon</h2><div class="ed-action">{{ $this->details['next_action'] }}</div></section>

        <section class="ed-card ed-wide"><h2>Eksik Bilgi ve Belgeler</h2>
            @if(count($this->dossier['missing']))
                <div class="ed-checks">@foreach($this->dossier['missing'] as $missing)
                    @php $missingLabel=match($missing){'property_type'=>'Taşınmaz türü','city'=>'İl','location'=>'Konum','area_sqm'=>'Metrekare','asking_price'=>'Satıcı fiyatı','property_identity'=>'Ada/parsel veya konum','property_photo'=>'Güncel arsa fotoğrafları','zoning_context'=>'İmar bilgisi','title_deed_context'=>'Tapu bilgisi','listing_reference'=>'İlan bağlantısı',default=>$missing}; @endphp
                    <span class="ed-check">{{ $missingLabel }}</span>
                @endforeach</div>
            @else<div class="ed-ok">Kayıtlı dosyada kritik eksik görünmüyor.</div>@endif
        </section>

        <section class="ed-card ed-wide"><h2>Fotoğraflar ve Belgeler</h2><div class="ed-gallery-groups">
            @foreach($this->mediaGallery as $group=>$items)
                <div class="ed-gallery-group"><h3>{{ mb_strtoupper($group) }} · {{ count($items) }}</h3>
                    @if(count($items))<div class="ed-gallery">@foreach($items as $media)
                        <a class="ed-media" href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer">
                            @if($media['is_image'])<img src="{{ $media['url'] }}" alt="{{ $group }}" loading="lazy">@else<span class="ed-media-file">📄 Belgeyi aç</span>@endif
                            <span class="ed-media-meta">{{ $media['summary'] ?: $media['received_at'] }}</span>
                        </a>
                    @endforeach</div>@else<div class="ed-media-empty">Henüz {{ mb_strtolower($group) }} gelmedi.</div>@endif
                </div>
            @endforeach
        </div></section>

        <section class="ed-card ed-wide"><h2>Son WhatsApp Konuşmaları</h2><div class="ed-chat">
            @forelse($this->messages as $message)
                <div class="ed-msg {{ $message->sender_type }}"><p>{{ $message->message }}</p><small>{{ $message->sender_type==='customer'?'Müşteri':($message->sender_type==='human'?'Operatör':'Emlak AI') }} · {{ $message->created_at?->format('d.m.Y H:i') }}</small></div>
            @empty<div class="ed-event"><small>Henüz kayıtlı WhatsApp konuşması yok.</small></div>@endforelse
        </div></section>

        <section class="ed-card"><h2>CRM Notları</h2><div class="ed-notes">{{ $this->customer->notes ?: 'Henüz not eklenmedi.' }}</div><div class="ed-noteform"><textarea wire:model="note" placeholder="Görüşme notunu yaz…"></textarea><button wire:click="saveNote">Notu kaydet</button></div></section>
        <section class="ed-card"><h2>Müşteri Geçmişi</h2><div class="ed-timeline">@forelse($this->activities as $activity)<div class="ed-event"><b>{{ $activity->title }}</b>@if($activity->description)<p>{{ $activity->description }}</p>@endif<small>{{ $activity->created_at?->format('d.m.Y H:i') }} · {{ $activity->actorName() }}</small></div>@empty<div class="ed-event"><small>Henüz CRM aktivitesi yok.</small></div>@endforelse</div></section>
    </div>
</div>
</x-filament-panels::page>
