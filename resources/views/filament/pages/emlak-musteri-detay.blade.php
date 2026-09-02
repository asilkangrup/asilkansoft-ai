<x-filament-panels::page>
<style>
.ed{--ink:#122018;--muted:#718078;--line:#e4ebe7;width:100%;max-width:1100px;margin:auto}.ed *{box-sizing:border-box}.ed-back{display:inline-flex;margin-bottom:12px;color:#087344;text-decoration:none;font-size:12px;font-weight:900}.ed-head{padding:21px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f3fcf7)}.ed-role{font-size:10px;font-weight:900;color:#087344}.ed-name{margin:5px 0 2px;font-size:25px;font-weight:900;color:var(--ink)}.ed-contact{font-size:13px;color:var(--muted)}.ed-call{display:inline-flex;margin-top:12px;padding:10px 13px;border-radius:11px;background:#eafaf2;color:#087344;text-decoration:none;font-size:12px;font-weight:900}
.ed-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.ed-card{min-width:0;padding:17px;border:1px solid var(--line);border-radius:18px;background:#fff}.ed-wide{grid-column:1/-1}.ed-card h2{margin:0 0 12px;font-size:14px;color:var(--ink)}.ed-data{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ed-data div{min-width:0;padding:10px;border-radius:11px;background:#f7faf8}.ed-data small{display:block;font-size:9px;font-weight:900;color:var(--muted)}.ed-data b{display:block;margin-top:4px;font-size:12px;line-height:1.35;color:#35463d;overflow-wrap:anywhere}.ed-action,.ed-script{padding:12px;border-left:3px solid #22c77a;border-radius:10px;background:#f4fbf7;font-size:12px;line-height:1.55;color:#405149}.ed-script{border-color:#d79a16;background:#fffaf0}
.ed-checks{display:flex;flex-wrap:wrap;gap:7px}.ed-check{padding:8px 10px;border-radius:999px;background:#fff1ed;color:#9a4635;font-size:10px;font-weight:850}.ed-ok{padding:12px;border-radius:11px;background:#f4fbf7;color:#087344;font-size:11px}
.ed-neg-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.ed-neg-summary div{padding:10px;border-radius:11px;background:#f7faf8}.ed-neg-summary small{display:block;font-size:8px;font-weight:900;color:var(--muted)}.ed-neg-summary b{display:block;margin-top:4px;font-size:11px;color:#35463d}.ed-private{background:#fff8e8!important;border:1px solid #f1dfaf}.ed-neg-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,2fr) auto;gap:8px;margin-top:12px;align-items:end}.ed-neg-field small{display:block;margin-bottom:5px;font-size:9px;font-weight:900;color:var(--muted)}.ed-neg-field select,.ed-neg-field input{width:100%;min-height:44px;padding:9px 11px;border:1px solid var(--line);border-radius:11px;background:#fff;color:var(--ink);font-size:14px}.ed-neg-form button{min-height:44px;padding:0 14px;border:0;border-radius:11px;background:#087344;color:#fff;font-weight:900}.ed-neg-help{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.ed-neg-history{display:grid;gap:7px;margin-top:12px}.ed-neg-history-item{padding:10px;border-radius:11px;background:#f7faf8}.ed-neg-history-item b{font-size:10px;color:#35463d}.ed-neg-history-item p{margin:4px 0;font-size:10px;color:#536159}.ed-neg-history-item small{font-size:8px;color:var(--muted)}
.ed-upload{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:8px;margin-bottom:16px;padding:12px;border-radius:13px;background:#f7faf8}.ed-upload input,.ed-upload select,.ed-owner select,.ed-owner input{width:100%;min-height:44px;padding:9px 11px;border:1px solid var(--line);border-radius:11px;background:#fff;color:var(--ink);font-size:14px}.ed-upload button,.ed-owner button{min-height:44px;padding:0 14px;border:0;border-radius:11px;background:var(--ink);color:#fff;font-weight:900}.ed-owner{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:8px;align-items:end}.ed-owner-field small{display:block;margin-bottom:5px;font-size:9px;font-weight:900;color:var(--muted)}.ed-legal{margin:9px 0 0;font-size:10px;line-height:1.45;color:#8a6951}.ed-gallery-groups{display:grid;gap:15px}.ed-gallery-group h3{margin:0 0 8px;font-size:11px;color:#536159}.ed-gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.ed-media{display:block;overflow:hidden;border:1px solid var(--line);border-radius:13px;background:#f7faf8;color:#315b9c;text-decoration:none}.ed-media img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}.ed-media-file,.ed-media-empty{display:grid;place-items:center;aspect-ratio:4/3;padding:12px;text-align:center;font-size:11px;font-weight:900}.ed-media-empty{border:1px dashed var(--line);border-radius:13px;color:var(--muted);background:#fafcfb}.ed-media-meta{display:block;padding:7px;font-size:9px;color:var(--muted)}
.ed-chat{display:grid;gap:8px;max-height:460px;overflow:auto}.ed-msg{max-width:86%;padding:10px 12px;border-radius:14px;background:#f2f5f3;color:#405149}.ed-msg.customer{justify-self:start;border-bottom-left-radius:4px}.ed-msg.ai,.ed-msg.human{justify-self:end;background:#eaf8f1;border-bottom-right-radius:4px}.ed-msg p{margin:0;white-space:pre-wrap;font-size:11px;line-height:1.5}.ed-msg small{display:block;margin-top:5px;font-size:8px;color:var(--muted)}
.ed-notes{white-space:pre-wrap;font-size:12px;line-height:1.55;color:#4b5a52;max-height:180px;overflow:auto}.ed-noteform{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:10px}.ed-noteform textarea{min-height:70px;padding:11px;border:1px solid var(--line);border-radius:12px;resize:vertical}.ed-noteform button{padding:0 15px;border:0;border-radius:12px;background:var(--ink);color:#fff;font-weight:900}.ed-timeline{display:grid;gap:8px}.ed-event{padding:11px;border-radius:12px;background:#f7faf8}.ed-event b{font-size:11px;color:#35463d}.ed-event p{margin:4px 0;font-size:11px;line-height:1.45;color:#536159}.ed-event small{font-size:9px;color:var(--muted)}
@media(max-width:720px){.ed-upload,.ed-owner{grid-template-columns:1fr}.ed-upload input,.ed-upload select,.ed-owner select,.ed-owner input{font-size:16px}.ed-upload button,.ed-owner button{width:100%}.ed-grid{grid-template-columns:minmax(0,1fr)}.ed-wide{grid-column:auto}.ed-name{font-size:22px}.ed-card{padding:14px}.ed-data{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-noteform{grid-template-columns:1fr}.ed-noteform textarea{font-size:16px}.ed-noteform button{height:44px}.ed-call{width:100%;justify-content:center}.ed-msg{max-width:92%}}
@media(max-width:720px){.ed-neg-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-neg-form,.ed-neg-help{grid-template-columns:1fr}.ed-neg-field select,.ed-neg-field input{font-size:16px}.ed-neg-form button{width:100%}}
@media(max-width:380px){.ed-data,.ed-neg-summary{grid-template-columns:1fr}}
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

        <section class="ed-card ed-wide"><h2>Tapu Kimin Üzerine?</h2>
            <div class="ed-owner">
                <label class="ed-owner-field"><small>SAHİPLİK İLİŞKİSİ</small><select wire:model="titleOwnerRelation">
                    <option value="unknown">Henüz bilinmiyor</option><option value="seller">Satıcının kendisi</option>
                    <option value="spouse">Eşi</option><option value="relative">Yakını / akrabası</option>
                    <option value="company">Şirket</option><option value="other_person">Başka bir kişi</option>
                </select></label>
                <label class="ed-owner-field"><small>KISA AÇIKLAMA</small><input wire:model="titleOwnerNote" maxlength="300" placeholder="Örn. babasının üzerine"></label>
                <button wire:click="saveTitleOwnership" wire:loading.attr="disabled">Kaydet</button>
            </div>
            <p class="ed-legal">Bu alan görüşme sırasında alınan operatör beyanıdır; resmi tapu doğrulaması yerine geçmez.</p>
        </section>

        <section class="ed-card"><h2>Telefonda Söyle</h2><div class="ed-script">{{ $this->dossier['call_script'] }}</div></section>
        <section class="ed-card"><h2>Sıradaki Aksiyon</h2><div class="ed-action">{{ $this->details['next_action'] }}</div></section>

        @if(count($this->negotiationWorkspace))
        <section class="ed-card ed-wide"><h2>Satıcı Pazarlık Masası</h2>
            <div class="ed-neg-summary">
                @foreach([
                    'Satıcı beklentisi'=>$this->negotiationWorkspace['asking_price'],
                    'Gerçekçi satış alt'=>$this->negotiationWorkspace['realistic_sale_min'],
                    'Gerçekçi satış üst'=>$this->negotiationWorkspace['realistic_sale_max'],
                    'Yatırımcı hedef alt'=>$this->negotiationWorkspace['investor_target_min'],
                    'Yatırımcı hedef üst'=>$this->negotiationWorkspace['investor_target_max'],
                ] as $label=>$amount)
                    <div><small>{{ mb_strtoupper($label) }}</small><b>{{ $amount ? number_format($amount,0,',','.').' TL' : 'Araştırma bekleniyor' }}</b></div>
                @endforeach
                <div class="ed-private"><small>GİZLİ SATICI TABANI · YALNIZ OPERATÖR</small><b>{{ $this->negotiationWorkspace['confidential_floor'] ? number_format($this->negotiationWorkspace['confidential_floor'],0,',','.').' TL' : 'Beyan edilmedi' }}</b></div>
            </div>
            <div class="ed-neg-help">
                <div class="ed-action">{{ $this->negotiationWorkspace['recommended_action'] }}</div>
                <div class="ed-script">{{ $this->negotiationWorkspace['call_script'] }}</div>
            </div>
            <div class="ed-neg-form">
                <label class="ed-neg-field"><small>GÖRÜŞME SONUCU</small><select wire:model="negotiationOutcome">
                    <option value="not_reached">Ulaşılamadı</option>
                    <option value="needs_time">Düşünmek için süre istedi</option>
                    <option value="willing_to_negotiate">Pazarlığa açık</option>
                    <option value="counter_offer">Karşı teklif verdi</option>
                    <option value="accepted_real_offer">Gerçek teklifi kabul etti</option>
                    <option value="rejected_real_offer">Gerçek teklifi reddetti</option>
                </select></label>
                <label class="ed-neg-field"><small>TEYİT EDİLEN TUTAR</small><input wire:model="negotiationAmount" inputmode="numeric" placeholder="Örn. 3500000"></label>
                <label class="ed-neg-field"><small>GÖRÜŞME NOTU</small><input wire:model="negotiationNote" maxlength="1000" placeholder="Gerekçe, şart veya sonraki adım"></label>
                <button wire:click="saveSellerNegotiation" wire:loading.attr="disabled">Sonucu kaydet</button>
            </div>
            @error('negotiationAmount')<div class="ed-check" style="margin-top:9px">{{ $message }}</div>@enderror
            @error('negotiationNote')<div class="ed-check" style="margin-top:9px">{{ $message }}</div>@enderror
            <p class="ed-legal">Kayıt yalnız CRM ve operatör ekranında tutulur; otomatik WhatsApp mesajı veya müşteri takibi oluşturmaz. Gizli satıcı tabanı yatırımcıya gösterilmez.</p>
            @if(count($this->negotiationWorkspace['history']))
                <div class="ed-neg-history">@foreach($this->negotiationWorkspace['history'] as $event)
                    <div class="ed-neg-history-item"><b>{{ $event['title'] }}</b><p>{{ $event['description'] }}</p><small>{{ $event['created_at'] }} · {{ $event['actor'] }}</small></div>
                @endforeach</div>
            @endif
        </section>
        @endif

        <section class="ed-card ed-wide"><h2>Eksik Bilgi ve Belgeler</h2>
            @if(count($this->dossier['missing']))
                <div class="ed-checks">@foreach($this->dossier['missing'] as $missing)
                    @php $missingLabel=match($missing){'property_type'=>'Taşınmaz türü','city'=>'İl','location'=>'Konum','area_sqm'=>'Metrekare','asking_price'=>'Satıcı fiyatı','property_identity'=>'Ada/parsel veya konum','property_photo'=>'Güncel arsa fotoğrafları','zoning_context'=>'İmar bilgisi','title_deed_context'=>'Tapu bilgisi','title_owner_relation'=>'Tapu kimin üzerine','listing_reference'=>'İlan bağlantısı',default=>$missing}; @endphp
                    <span class="ed-check">{{ $missingLabel }}</span>
                @endforeach</div>
            @else<div class="ed-ok">Kayıtlı dosyada kritik eksik görünmüyor.</div>@endif
        </section>

        <section class="ed-card ed-wide"><h2>Fotoğraflar ve Belgeler</h2>
            <div class="ed-upload">
                <input type="file" wire:model="manualUpload" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <select wire:model="manualMediaCategory">
                    <option value="property_photo">Arsa / taşınmaz fotoğrafı</option><option value="title_deed">Tapu belgesi</option>
                    <option value="parcel_document">Parsel belgesi</option><option value="listing">İlan görseli</option>
                </select>
                <button wire:click="uploadManualMedia" wire:loading.attr="disabled" wire:target="manualUpload,uploadManualMedia">Dosyaya ekle</button>
            </div>
            @error('manualUpload')<div class="ed-check" style="margin-bottom:10px">{{ $message }}</div>@enderror
            <div class="ed-gallery-groups">
            @foreach($this->mediaGallery as $group=>$items)
                <div class="ed-gallery-group"><h3>{{ mb_strtoupper($group) }} · {{ count($items) }}</h3>
                    @if(count($items))<div class="ed-gallery">@foreach($items as $media)
                        <a class="ed-media" href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer">
                            @if($media['is_image'])<img src="{{ $media['url'] }}" alt="{{ $group }}" loading="lazy">@else<span class="ed-media-file">📄 Belgeyi aç</span>@endif
                            <span class="ed-media-meta">{{ $media['source'] }} · {{ $media['summary'] ?: $media['received_at'] }}</span>
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
