<x-filament-panels::page>
<style>
.ek{--ink:#14211a;--muted:#718078;--line:#e2e9e5;display:grid;grid-template-columns:310px minmax(0,1fr);gap:16px;color:var(--ink)}
.ek-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 9px 28px rgba(15,35,25,.04)}.ek-side{padding:15px;align-self:start;position:sticky;top:15px}.ek-side h2{font-size:18px;font-weight:900}.ek-search{width:100%;margin:10px 0;border:1px solid var(--line)!important;border-radius:13px!important}
.ek-deal{display:block;width:100%;text-align:left;border:1px solid var(--line);background:#f8faf9;border-radius:14px;padding:12px;margin-top:8px}.ek-deal.active{border-color:#178c4d;background:#eef9f2}.ek-deal b,.ek-deal small{display:block}.ek-deal small{margin-top:4px;color:var(--muted)}
.ek-main{padding:22px}.ek-head{display:flex;justify-content:space-between;gap:12px}.ek-head h1{font-size:25px;font-weight:900}.ek-head p{margin-top:5px;color:var(--muted)}.ek-status{height:30px;padding:0 11px;border-radius:999px;display:inline-flex;align-items:center;background:#eef4ff;color:#315b9c;font-size:11px;font-weight:900}
.ek-price{margin:16px 0;padding:14px;border-radius:15px;background:#f2f8f4}.ek-price small,.ek-field label{display:block;color:var(--muted);font-size:11px;font-weight:900}.ek-price b{display:block;font-size:22px;margin-top:4px}
.ek-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.ek-field input,.ek-field textarea{width:100%;border:1px solid var(--line)!important;border-radius:12px!important}.ek-field label{margin-bottom:5px}.ek-wide{grid-column:1/-1}
.ek-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin:17px 0}.ek-check{display:flex;gap:9px;padding:12px;border-radius:13px;background:#f6f9f7}.ek-check input{margin-top:3px}.ek-check b{font-size:12px}.ek-check small{display:block;color:var(--muted);font-size:10px;margin-top:3px}
.ek-progress{margin-top:16px;border:1px solid var(--line);border-radius:15px;padding:13px}.ek-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed #ebefed}.ek-row:last-child{border:0}.ek-ok{color:#11804a;font-weight:900}.ek-no{color:#a84242;font-weight:900}
.ek-save{margin-top:16px;border:0;border-radius:13px;background:#168a4b;color:#fff;padding:12px 17px;font-weight:900}.ek-note{margin-top:13px;padding:12px;border-radius:12px;background:#fff7e5;color:#6f5415;font-size:12px}.ek-empty{padding:35px;text-align:center;color:var(--muted)}
@media(max-width:820px){.ek{grid-template-columns:1fr}.ek-side{position:static}.ek-main{padding:17px}.ek-head{display:block}.ek-status{margin-top:9px}.ek-grid,.ek-checks{grid-template-columns:1fr}.ek-wide{grid-column:auto}}
</style>

<div class="ek">
    <aside class="ek-card ek-side">
        <h2>Anlaşma sağlanan dosyalar</h2>
        <input class="ek-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Taşınmaz veya taraf ara">
        @forelse($this->deals as $deal)
            @php
                $case=app(AppServicesRealEstateClosingService::class)->caseForPair($deal['seller_profile_id'],$deal['investor_profile_id']);
                $property=collect([data_get($deal['seller']->data,'location'),data_get($deal['seller']->data,'property_type')])->filter()->implode(' · ');
            @endphp
            <button class="ek-deal {{ $selectedKey === $deal['key'] ? 'active' : '' }}" wire:click="selectDeal('{{ $deal['key'] }}')">
                <b>{{ $property ?: 'Taşınmaz' }}</b>
                <small>{{ $deal['seller']->conversation?->customer_name ?: 'Satıcı' }} ↔ {{ $deal['investor']->conversation?->customer_name ?: 'Yatırımcı' }}</small>
                <small>{{ $case ? app(AppServicesRealEstateClosingService::class)->statusLabel($case->status) : 'Kapanış dosyası açılacak' }}</small>
            </button>
        @empty
            <div class="ek-empty">Kapanışa aktarılabilecek kabul edilmiş gerçek teklif bulunmuyor.</div>
        @endforelse
    </aside>

    <main class="ek-card ek-main">
        @if($this->selectedDeal)
            <div class="ek-head">
                <div>
                    <h1>Tapu ve İşlem Kapanışı</h1>
                    <p>{{ $this->selectedDeal['seller']->conversation?->customer_name ?: 'Satıcı' }} ↔ {{ $this->selectedDeal['investor']->conversation?->customer_name ?: 'Yatırımcı' }}</p>
                </div>
                <div class="ek-status">{{ $this->statusLabel }}</div>
            </div>

            <div class="ek-price">
                <small>KABUL EDİLEN GERÇEK TEKLİF</small>
                <b>{{ $agreedPrice ? number_format((int)$agreedPrice,0,',','.') .' TL' : 'Tutarı doğrulayın' }}</b>
            </div>

            <div class="ek-grid">
                <div class="ek-field">
                    <label>Anlaşılan satış bedeli (TL)</label>
                    <input type="number" min="1" wire:model="agreedPrice">
                    @error('agreed_price')<small style="color:#b32b2b">{{ $message }}</small>@enderror
                </div>
                <div class="ek-field">
                    <label>Kapora tutarı (TL)</label>
                    <input type="number" min="1" wire:model="depositAmount">
                    @error('deposit_amount')<small style="color:#b32b2b">{{ $message }}</small>@enderror
                </div>
                <div class="ek-field">
                    <label>Tapu randevusu</label>
                    <input type="datetime-local" wire:model="appointmentAt">
                </div>
                <div class="ek-field">
                    <label>Tapu müdürlüğü / randevu yeri</label>
                    <input type="text" wire:model="appointmentLocation">
                </div>
                <div class="ek-field ek-wide">
                    <label>Operatör kapanış notu (yalnız CRM içinde)</label>
                    <textarea rows="3" wire:model="operatorNote"></textarea>
                </div>
            </div>

            <div class="ek-checks">
                <label class="ek-check"><input type="checkbox" wire:model="titleDeedVerified"><span><b>Tapu belgesi doğrulandı</b><small>Taşınmaz ve malik bilgileri dosyayla eşleşiyor.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="identityAuthorityVerified"><span><b>Kimlik ve işlem yetkisi doğrulandı</b><small>Taraflar veya geçerli temsilcileri kontrol edildi.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="encumbranceChecked"><span><b>Takyidat kontrol edildi</b><small>İpotek, haciz ve kısıtlamalar operatörce incelendi.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="taxFeeChecked"><span><b>Harç ve masraflar kontrol edildi</b><small>Tarafların ödeme sorumlulukları netleştirildi.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="paymentMethodConfirmed"><span><b>Güvenli ödeme planı teyit edildi</b><small>Ödeme kanalı ve zamanlaması taraflarca doğrulandı.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="depositReceived"><span><b>Kapora alındı</b><small>Yalnız gerçek tahsilat operatörce doğrulandıysa işaretleyin.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="finalPaymentVerified"><span><b>Nihai ödeme doğrulandı</b><small>Banka veya güvenli ödeme kaydı görülmeden işaretlemeyin.</small></span></label>
                <label class="ek-check"><input type="checkbox" wire:model="deedTransferCompleted"><span><b>Tapu devri tamamlandı</b><small>Resmî devir sonucu operatörce doğrulandı.</small></span></label>
            </div>

            <div class="ek-progress">
                @foreach($this->checklist as $label => $done)
                    <div class="ek-row"><span>{{ $label }}</span><span class="{{ $done ? 'ek-ok' : 'ek-no' }}">{{ $done ? 'Tamam' : 'Bekliyor' }}</span></div>
                @endforeach
            </div>

            <button class="ek-save" type="button" wire:click="save">Kapanış Dosyasını Kaydet</button>
            <div class="ek-note">Bu ekran yalnız operatör içi işlem takibidir. Müşteriye otomatik mesaj göndermez, takip planlamaz ve ödeme/devir tamamlandı beyanını insan doğrulaması olmadan üretmez.</div>
        @else
            <div class="ek-empty">Kapanış yönetimi için kabul edilmiş bir teklif dosyası seçin.</div>
        @endif
    </main>
</div>
</x-filament-panels::page>
