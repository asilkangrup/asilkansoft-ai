<x-filament-panels::page>
<style>
.re-work{--g:#22c77a;--ink:#122018;--muted:#708078;--line:#e4ebe7;max-width:1180px;margin:auto}
.re-hero{padding:24px;border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,#f3fcf7);margin-bottom:14px}
.re-hero small{color:#087344;font-weight:800}.re-hero h1{font-size:28px;font-weight:900;color:var(--ink);margin:6px 0}.re-hero p{color:var(--muted);margin:0;line-height:1.55}
.re-grid{display:grid;gap:13px}.re-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:0 10px 30px rgba(15,40,25,.04)}
.re-top{display:flex;justify-content:space-between;gap:12px}.re-name{font-weight:900;font-size:17px;color:var(--ink)}.re-property{font-size:13px;color:var(--muted);margin-top:3px}
.re-badge{height:30px;padding:0 10px;display:flex;align-items:center;border-radius:999px;background:#eafaf2;color:#087344;font-size:11px;font-weight:900;white-space:nowrap}.re-badge.seller{background:#fff5df;color:#8a5a00}.re-badge.offer{background:#eef4ff;color:#2956a3}.re-badge.final{background:#f2ecff;color:#6f3fb3}
.re-box{padding:13px;border-radius:14px;background:#f7faf8;margin-top:12px}.re-box b{font-size:12px;color:var(--ink)}.re-box p{font-size:13px;line-height:1.55;color:#3c4c44;margin:5px 0 0}.re-script{border-left:3px solid var(--g)}
.re-offer{display:inline-flex;margin-top:10px;padding:7px 10px;border-radius:10px;background:#eef4ff;color:#2956a3;font-size:12px;font-weight:900}.re-reminder{display:inline-flex;margin:10px 0 0 6px;padding:7px 10px;border-radius:10px;background:#fff4dd;color:#875900;font-size:12px;font-weight:900}
.re-actions{display:grid;grid-template-columns:170px 170px 190px minmax(220px,1fr) auto;gap:9px;margin-top:12px}.re-actions select,.re-actions textarea,.re-actions input{border:1px solid #dce5e0;border-radius:12px;background:#fff;font-size:13px}.re-actions select,.re-actions input{height:44px;padding:0 10px}.re-actions textarea{min-height:44px;padding:11px;resize:vertical}
.re-save,.re-call{border:0;border-radius:12px;font-weight:850;cursor:pointer}.re-save{padding:0 16px;background:var(--ink);color:#fff}.re-call{display:inline-flex;margin-top:12px;padding:10px 13px;background:#eafaf2;color:#087344;text-decoration:none}.re-empty{text-align:center;padding:45px;border:1px dashed var(--line);border-radius:20px;color:var(--muted)}
@media(max-width:850px){.re-actions{grid-template-columns:1fr 1fr}.re-actions textarea{grid-column:1/-1}.re-save{height:46px}}
@media(max-width:700px){.re-hero{padding:19px}.re-hero h1{font-size:23px}.re-card{padding:15px}.re-top{align-items:flex-start}.re-actions{grid-template-columns:1fr}.re-actions textarea{grid-column:auto}.re-actions select,.re-actions input{font-size:16px}.re-save{height:46px}.re-call{width:100%;justify-content:center}}
</style>
<div class="re-work">
    <section class="re-hero">
        <small>BUGÜN NE YAPMALIYIM?</small>
        <h1>Emlak İş Merkezi</h1>
        <p>Gerçek satıcı ve yatırımcı verilerine göre sıradaki kişiyi, aranma nedenini ve söyleyeceğin metni gösterir. Görüşme sonucu kaydedildikçe sonraki adım otomatik değişir; müşteriye otomatik takip mesajı gönderilmez.</p>
    </section>

    <div class="re-grid">
        @forelse($this->tasks as $task)
            @php
                $badge = match($task['kind']) {
                    'investor' => ['YATIRIMCIYI ARA', ''],
                    'seller_offer' => ['TEKLİFİ SATICIYA İLET', 'offer'],
                    'investor_counter' => ['KARŞI TEKLİFİ İLET', 'offer'],
                    'seller_final' => ['SON TEYİDİ AL', 'final'],
                    'seller' => ['DOSYAYI TAMAMLA', 'seller'],
                    default => ['YENİ YATIRIMCI BUL', 'seller'],
                };
                $needsMoneyInput = in_array($task['kind'], ['investor', 'seller_offer', 'investor_counter'], true);
            @endphp

            <article class="re-card" wire:key="{{ $task['key'] }}">
                <div class="re-top">
                    <div>
                        <div class="re-name">{{ $task['target_name'] }}</div>
                        <div class="re-property">{{ $task['property'] }}</div>
                    </div>
                    <span class="re-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>

                <div class="re-box"><b>NEDEN?</b><p>{{ $task['reason'] }}</p></div>
                <div class="re-box"><b>HEDEF</b><p>{{ $task['action'] }}</p></div>
                <div class="re-box re-script"><b>SÖYLENECEK METİN</b><p>“{{ $task['script'] }}”</p></div>

                @if(!empty($task['offer_amount']))
                    <div class="re-offer">Mevcut rakam: {{ number_format((int) $task['offer_amount'], 0, ',', '.') }} TL</div>
                @endif

                @if(!empty($task['reminder_at']))
                    <div class="re-reminder">⏰ Tekrar ara: {{ $task['reminder_at']->format('d.m.Y H:i') }}</div>
                @endif

                @if($task['phone'])
                    <a class="re-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $task['phone']) }}">📞 {{ $task['phone'] }} numarasını ara</a>
                @endif

                <div class="re-actions">
                    <select wire:model="callResults.{{ $task['key'] }}">
                        <option value="">Görüşme sonucu</option>
                        @foreach($this->outcomesFor($task['kind']) as $outcome)
                            <option value="{{ $outcome }}">{{ $outcome }}</option>
                        @endforeach
                    </select>

                    @if($needsMoneyInput)
                        <input
                            inputmode="numeric"
                            wire:model="offerAmounts.{{ $task['key'] }}"
                            placeholder="Teklif / karşı teklif TL"
                        >
                    @else
                        <div></div>
                    @endif

                    <input
                        type="datetime-local"
                        wire:model="reminderDates.{{ $task['key'] }}"
                        title="Tekrar arama zamanı"
                        aria-label="Tekrar arama zamanı"
                    >

                    <textarea wire:model="callNotes.{{ $task['key'] }}" placeholder="Görüşme notunu yaz…"></textarea>
                    <button class="re-save" wire:click="saveCall('{{ $task['key'] }}')">Kaydet</button>
                </div>
            </article>
        @empty
            <div class="re-empty">Şu anda yönlendirilecek aktif emlak görevi bulunmuyor.</div>
        @endforelse
    </div>
</div>
</x-filament-panels::page>
