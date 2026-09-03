<x-filament-panels::page>
<style>
.ey-shell{display:grid;grid-template-columns:300px minmax(0,1fr);gap:18px;color:#17221d}
.ey-card{background:#fff;border:1px solid #dde6e0;border-radius:22px;box-shadow:0 8px 25px rgba(20,45,31,.05)}
.ey-sidebar{padding:16px;align-self:start;position:sticky;top:16px}.ey-search{width:100%;border:1px solid #dbe4de!important;border-radius:14px!important;margin:10px 0}
.ey-record{display:block;width:100%;text-align:left;padding:13px;border:1px solid #e4ebe7;border-radius:15px;background:#f8faf9;margin-top:8px}.ey-record.active{border-color:#168a4b;background:#eef9f2}.ey-record b,.ey-record small{display:block}.ey-record small{color:#728078;margin-top:4px}
.ey-main{padding:22px}.ey-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:18px}.ey-title{font-size:25px;font-weight:900}.ey-sub{color:#6e7b74;margin-top:5px}.ey-status{padding:8px 12px;border-radius:999px;background:#fff4d7;color:#865c00;font-weight:800;font-size:12px}.ey-status.ready{background:#e8f8ee;color:#147943}.ey-status.expired,.ey-status.review_required{background:#feecec;color:#a22525}
.ey-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ey-field label{display:block;font-size:12px;font-weight:800;color:#56645c;margin-bottom:6px}.ey-input{width:100%;border:1px solid #dbe4de!important;border-radius:13px!important}.ey-wide{grid-column:1/-1}
.ey-checks{display:grid;gap:9px;margin-top:18px}.ey-check{display:flex;gap:10px;align-items:flex-start;padding:12px;border-radius:13px;background:#f5f8f6}.ey-check input{margin-top:4px}.ey-check b{display:block}.ey-check small{display:block;color:#738078;margin-top:2px}
.ey-summary{margin-top:20px;padding:15px;border:1px solid #e0e7e3;border-radius:15px}.ey-line{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #edf1ef}.ey-line:last-child{border:0}.ey-ok{color:#148047;font-weight:900}.ey-no{color:#ad3131;font-weight:900}
.ey-save{margin-top:18px;border:0;border-radius:13px;padding:12px 18px;background:#168a4b;color:#fff;font-weight:900}.ey-note{margin-top:14px;padding:13px;border-radius:13px;background:#fff8e8;color:#705410;font-size:13px}
.ey-empty{padding:30px;text-align:center;color:#69766f}
@media(max-width:850px){.ey-shell{grid-template-columns:1fr}.ey-sidebar{position:static}.ey-main{padding:17px}.ey-grid{grid-template-columns:1fr}.ey-wide{grid-column:auto}.ey-head{display:block}.ey-status{display:inline-block;margin-top:10px}}
</style>

<div class="ey-shell">
    <aside class="ey-card ey-sidebar">
        <h2 style="font-size:18px;font-weight:900">Satıcı dosyaları</h2>
        <input class="ey-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Satıcı, telefon veya konum ara">
        @forelse($this->profiles as $profile)
            @php
                $data=is_array($profile->data)?$profile->data:[];
                $control=is_array(data_get($data,'authorization_control'))?data_get($data,'authorization_control'):[];
                $location=collect([data_get($data,'city'),data_get($data,'district'),data_get($data,'property_type')])->filter()->implode(' · ');
            @endphp
            <button class="ey-record {{ $this->selectedProfileId === $profile->id ? 'active' : '' }}" wire:click="selectProfile({{ $profile->id }})">
                <b>{{ $profile->conversation?->customer_name ?: 'İsimsiz satıcı' }}</b>
                <small>{{ $location ?: 'Dosya bilgileri tamamlanıyor' }}</small>
                <small>{{ $control['status_label'] ?? 'Yetkilendirme kontrolü bekliyor' }}</small>
            </button>
        @empty
            <div class="ey-empty">Satıcı dosyası bulunamadı.</div>
        @endforelse
    </aside>

    <main class="ey-card ey-main">
        @if($this->selectedProfile)
            <div class="ey-head">
                <div>
                    <div class="ey-title">Yetkilendirme ve Sözleşme Kontrolü</div>
                    <div class="ey-sub">{{ $this->selectedProfile->conversation?->customer_name ?: 'İsimsiz satıcı' }} · Dosya #{{ $this->selectedProfile->id }}</div>
                </div>
                <div class="ey-status {{ $this->control['status'] ?? 'incomplete' }}">{{ $this->control['status_label'] ?? 'Eksik kontrol bulunuyor' }}</div>
            </div>

            <div class="ey-grid">
                <div class="ey-field">
                    <label>Yetkilendirme türü</label>
                    <select class="ey-input" wire:model="mandateType">
                        <option value="none">Yetki alınmadı</option>
                        <option value="non_exclusive">Tek yetkili değil</option>
                        <option value="exclusive">Tek yetkili</option>
                    </select>
                </div>
                <div class="ey-field">
                    <label>Sözleşme başlangıç / imza tarihi</label>
                    <input class="ey-input" type="date" wire:model="signedAt">
                </div>
                <div class="ey-field">
                    <label>Yetki bitiş tarihi</label>
                    <input class="ey-input" type="date" wire:model="expiresAt">
                </div>
                <div class="ey-field ey-wide">
                    <label>Operatör notu (yalnız CRM içinde)</label>
                    <textarea class="ey-input" rows="3" wire:model="operatorNote" placeholder="Eksik belge, vekâlet veya hukuki kontrol notu"></textarea>
                </div>
            </div>

            <div class="ey-checks">
                <label class="ey-check"><input type="checkbox" wire:model="sellerPresentationConsent"><span><b>Yatırımcı sunum onayı alındı</b><small>Satıcı dosyanın gerçek yatırımcılara sunulmasına yazılı onay verdi.</small></span></label>
                <label class="ey-check"><input type="checkbox" wire:model="commissionTermsAcknowledged"><span><b>Satıcı %2 hizmet bedelini kabul etti</b><small>Alıcı tarafı hizmet bedeli de %2 olarak sabittir.</small></span></label>
                <label class="ey-check"><input type="checkbox" wire:model="titleOwnerConfirmed"><span><b>Tapu sahibi ilişkisi doğrulandı</b><small>Satıcı tapu sahibi, yetkili temsilci veya geçerli vekil olarak doğrulandı.</small></span></label>
                <label class="ey-check"><input type="checkbox" wire:model="authorizationDocumentPresent"><span><b>Yetkilendirme belgesi dosyada mevcut</b><small>İmzalı belgenin CRM dosyasında bulunduğu operatörce kontrol edildi.</small></span></label>
                <label class="ey-check"><input type="checkbox" wire:model="legalReviewRequired"><span><b>Hukuki / operasyonel inceleme gerekli</b><small>İşaretliyse dosya sunuma hazır sayılmaz.</small></span></label>
            </div>

            <div class="ey-summary">
                @foreach($this->checklist as $label => $complete)
                    <div class="ey-line"><span>{{ $label }}</span><span class="{{ $complete ? 'ey-ok' : 'ey-no' }}">{{ $complete ? 'Tamam' : 'Eksik' }}</span></div>
                @endforeach
            </div>

            <button class="ey-save" type="button" wire:click="save">Kontrolü CRM’e Kaydet</button>
            <div class="ey-note">Bu ekran hukuki onay vermez; operatör kontrol listesidir. Kayıt, müşteriye otomatik WhatsApp veya takip mesajı göndermez.</div>
        @else
            <div class="ey-empty">Kontrol için soldan izole bir satıcı dosyası seçin.</div>
        @endif
    </main>
</div>
</x-filament-panels::page>
