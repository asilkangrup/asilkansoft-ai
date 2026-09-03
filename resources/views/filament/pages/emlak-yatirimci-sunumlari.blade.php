<x-filament-panels::page>
<style>
.ep-shell{display:grid;grid-template-columns:310px minmax(0,1fr);gap:18px;color:#17221d}
.ep-card{background:#fff;border:1px solid #dde6e0;border-radius:22px;box-shadow:0 8px 25px rgba(20,45,31,.05)}
.ep-sidebar{padding:16px;align-self:start;position:sticky;top:16px}
.ep-search{width:100%;border:1px solid #dbe4de!important;border-radius:14px!important;margin-bottom:12px}
.ep-record{display:block;width:100%;text-align:left;padding:13px;border:1px solid #e4ebe7;border-radius:15px;background:#f8faf9;margin-top:8px}
.ep-record.active{border-color:#168a4b;background:#eef9f2}
.ep-record b,.ep-record small{display:block}.ep-record small{color:#728078;margin-top:4px}
.ep-sheet{padding:28px}
.ep-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:2px solid #183f2b;padding-bottom:20px}
.ep-brand{font-size:13px;font-weight:900;letter-spacing:.08em;color:#168a4b}.ep-title{font-size:28px;font-weight:900;line-height:1.15;margin:7px 0}
.ep-ref{color:#6f7a74;font-size:12px}.ep-badge{background:#eaf8ef;color:#147b43;border-radius:999px;padding:8px 11px;font-weight:800;font-size:12px}
.ep-actions{display:flex;gap:8px;margin:18px 0}.ep-actions button{border:0;border-radius:12px;padding:11px 16px;background:#168a4b;color:#fff;font-weight:800}
.ep-section{margin-top:22px}.ep-section h2{font-size:17px;font-weight:900;margin-bottom:11px}
.ep-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ep-fact{background:#f5f8f6;border-radius:13px;padding:12px}
.ep-fact small,.ep-fact b{display:block}.ep-fact small{color:#748078;font-weight:700;font-size:11px;text-transform:uppercase}.ep-fact b{margin-top:5px}
.ep-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ep-photo{border:1px solid #e0e7e3;border-radius:14px;overflow:hidden;background:#f7f9f8}.ep-photo img{display:block;width:100%;height:240px;object-fit:cover}.ep-photo p{font-size:12px;padding:9px;margin:0}
.ep-note{padding:14px;border-radius:14px;background:#f0f7f3;margin-top:10px}.ep-warning{background:#fff8e8}
.ep-empty{padding:30px;text-align:center;color:#69766f}
@media(max-width:850px){.ep-shell{grid-template-columns:1fr}.ep-sidebar{position:static}.ep-sheet{padding:18px}.ep-facts,.ep-gallery{grid-template-columns:1fr}.ep-photo img{height:210px}.ep-title{font-size:23px}}
@media print{
 body{background:#fff!important}.fi-sidebar,.fi-topbar,.fi-header,.ep-sidebar,.ep-actions{display:none!important}
 .fi-main,.fi-main-ctn{margin:0!important;padding:0!important}.ep-shell{display:block}.ep-card{border:0;box-shadow:none}.ep-sheet{padding:0}
 .ep-photo{break-inside:avoid}.ep-photo img{height:220px}.ep-note{break-inside:avoid}
}
</style>

<div class="ep-shell">
    <aside class="ep-card ep-sidebar">
        <h2 style="font-size:18px;font-weight:900;margin-bottom:12px">Satıcı dosyaları</h2>
        <input class="ep-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Konum veya taşınmaz ara">
        @forelse($this->profiles as $profile)
            @php
                $data=is_array($profile->data)?$profile->data:[];
                $label=collect([data_get($data,'city'),data_get($data,'district'),data_get($data,'property_type')])->filter()->implode(' · ');
            @endphp
            <button class="ep-record {{ $this->selectedProfileId === $profile->id ? 'active' : '' }}" wire:click="selectProfile({{ $profile->id }})">
                <b>{{ $profile->conversation?->customer_name ?: 'İsimsiz satıcı' }}</b>
                <small>{{ $label ?: 'Dosya bilgileri tamamlanıyor' }}</small>
            </button>
        @empty
            <div class="ep-empty">Satıcı dosyası bulunamadı.</div>
        @endforelse
    </aside>

    <main class="ep-card ep-sheet">
        @if($this->presentation)
            <div class="ep-top">
                <div>
                    <div class="ep-brand">ASILKAN GAYRİMENKUL · YATIRIMCI SUNUMU</div>
                    <div class="ep-title">{{ $this->presentation['title'] }}</div>
                    <div class="ep-ref">Dosya no: {{ $this->presentation['reference'] }} · Türkiye geneli hizmet</div>
                </div>
                <div class="ep-badge">Satıcı kimliği gizli</div>
            </div>

            <div class="ep-actions">
                <button type="button" onclick="window.print()">Yazdır / PDF Kaydet</button>
            </div>

            <section class="ep-section">
                <h2>Taşınmaz Bilgileri</h2>
                <div class="ep-facts">
                    @foreach($this->presentation['facts'] as $label => $value)
                        @if(filled($value))
                            <div class="ep-fact"><small>{{ $label }}</small><b>{{ $value }}</b></div>
                        @endif
                    @endforeach
                </div>
            </section>

            @if($this->presentation['photos']->isNotEmpty())
                <section class="ep-section">
                    <h2>Taşınmaz Fotoğrafları</h2>
                    <div class="ep-gallery">
                        @foreach($this->presentation['photos'] as $photo)
                            <div class="ep-photo">
                                <img src="{{ $photo['url'] }}" alt="Taşınmaz fotoğrafı">
                                <p>{{ $photo['summary'] ?: 'Taşınmaz fotoğrafı' }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="ep-section">
                <h2>Teklif Süreci</h2>
                <div class="ep-note">{{ $this->presentation['offer_note'] }}</div>
                <div class="ep-note">{{ $this->presentation['buyer_fee_note'] }}</div>
                <div class="ep-note ep-warning">{{ $this->presentation['disclaimer'] }}</div>
            </section>
        @else
            <div class="ep-empty">Sunum için soldan izole bir satıcı dosyası seçin.</div>
        @endif
    </main>
</div>
</x-filament-panels::page>
