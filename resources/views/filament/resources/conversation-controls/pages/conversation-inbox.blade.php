<x-filament-panels::page>
<style>
.wai-shell{display:grid;grid-template-columns:330px minmax(0,1fr) 300px;height:78vh;min-height:650px;overflow:hidden;border:1px solid #dfe5eb;border-radius:16px;background:#fff;box-shadow:0 10px 35px rgba(15,23,42,.08)}
.wai-side,.wai-crm{background:#fff;min-width:0;min-height:0;overflow:hidden}
.wai-side{border-right:1px solid #e5e7eb;display:flex;flex-direction:column}
.wai-crm{border-left:1px solid #e5e7eb;overflow-y:auto}
.wai-main{min-width:0;min-height:0;display:flex;flex-direction:column;background:#efeae2}
.wai-search{padding:12px;border-bottom:1px solid #e5e7eb}
.wai-search input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:10px;padding:11px 12px;background:#f8fafc}
.wai-filters{padding:9px 10px;border-bottom:1px solid #e5e7eb;display:flex;gap:5px;flex-wrap:wrap}
.wai-filter{border:1px solid #dbe3ec;border-radius:999px;background:#f8fafc;padding:6px 9px;font-size:10px;font-weight:700;cursor:pointer}
.wai-filter.active{background:#dcfce7;border-color:#86efac;color:#166534}
.wai-list{overflow:auto;flex:1}
.wai-chat{width:100%;border:0;border-bottom:1px solid #edf1f4;background:#fff;text-align:left;padding:13px;cursor:pointer}
.wai-chat:hover,.wai-chat.active{background:#f1f5f9}
.wai-chat-row{display:flex;gap:9px}
.wai-avatar{width:42px;height:42px;border-radius:50%;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;flex:none}
.wai-chat-body{min-width:0;flex:1}
.wai-chat-head{display:flex;justify-content:space-between;gap:8px}
.wai-name{font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wai-time{font-size:10px;color:#94a3b8;white-space:nowrap}
.wai-preview{margin-top:4px;font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wai-badge{font-size:9px;border-radius:999px;padding:3px 6px;background:#dcfce7;color:#166534;font-weight:800}
.wai-badge.human{background:#fef3c7;color:#92400e}
.wai-unread{display:inline-flex;min-width:18px;height:18px;border-radius:99px;background:#22c55e;color:#fff;align-items:center;justify-content:center;font-size:9px;font-weight:800}
.wai-header{height:64px;box-sizing:border-box;padding:10px 14px;background:#fff;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;gap:10px}
.wai-header-name{font-weight:800;font-size:14px}
.wai-header-number{font-size:10px;color:#94a3b8;margin-top:2px}
.wai-actions{display:flex;gap:6px;align-items:center}
.wai-btn{border:0;border-radius:8px;padding:8px 10px;color:#fff;font-size:11px;font-weight:800;cursor:pointer}
.wai-human{background:#f59e0b}.wai-ai{background:#16a34a}
.wai-messages{flex:1;overflow:auto;padding:18px}
.wai-date{display:flex;justify-content:center;margin:10px 0}.wai-date span{background:#fff;padding:5px 9px;border-radius:8px;color:#64748b;font-size:9px}
.wai-row{display:flex;margin-bottom:7px}.wai-row.customer{justify-content:flex-start}.wai-row.outgoing{justify-content:flex-end}
.wai-bubble{max-width:72%;padding:8px 10px;border-radius:9px;box-shadow:0 1px 1px rgba(0,0,0,.08);font-size:13px}
.wai-bubble.customer{background:#fff;border-top-left-radius:2px}.wai-bubble.ai{background:#d9fdd3;border-top-right-radius:2px}.wai-bubble.human{background:#dbeafe;border-top-right-radius:2px}
.wai-sender{font-size:9px;font-weight:800;opacity:.6;margin-bottom:3px}
.wai-text{white-space:pre-wrap;word-break:break-word;line-height:1.45}
.wai-meta{display:flex;justify-content:flex-end;align-items:center;gap:4px;margin-top:4px;font-size:9px;opacity:.6}
.wai-check{letter-spacing:-2px}
.wai-media{max-width:280px;max-height:260px;border-radius:8px;display:block}
.wai-audio{width:260px;max-width:100%}
.wai-doc{display:flex;gap:8px;align-items:center;padding:9px;border-radius:8px;background:rgba(0,0,0,.04);text-decoration:none;color:inherit}
.wai-composer{padding:10px;background:#f0f2f5;border-top:1px solid #d1d5db}
.wai-tools{display:flex;gap:4px;align-items:center;position:relative;margin-bottom:6px}
.wai-icon{border:0;background:transparent;border-radius:50%;font-size:18px;width:32px;height:32px;cursor:pointer}
.wai-icon:hover{background:#e5e7eb}
.wai-emoji{position:absolute;bottom:36px;left:0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px;display:grid;grid-template-columns:repeat(6,35px);gap:4px;box-shadow:0 10px 30px rgba(0,0,0,.15);z-index:30}
.wai-emoji button{border:0;background:transparent;font-size:20px;border-radius:7px;cursor:pointer}
.wai-emoji button:hover{background:#f1f5f9}
.wai-quick{display:flex;gap:5px;overflow:auto;margin-bottom:7px}
.wai-quick button{white-space:nowrap;border:1px solid #dbe3ec;background:#fff;border-radius:999px;padding:5px 8px;font-size:9px;cursor:pointer}
.wai-compose-row{display:flex;gap:7px;align-items:flex-end}
.wai-compose-row textarea{flex:1;min-height:46px;max-height:130px;resize:none;border:1px solid #d1d5db;border-radius:12px;padding:11px;background:#fff}
.wai-send{border:0;border-radius:12px;background:#16a34a;color:#fff;font-weight:800;padding:0 17px;height:46px;cursor:pointer}
.wai-hint{font-size:9px;color:#94a3b8;margin-top:5px;display:flex;justify-content:space-between}
.wai-typing{font-size:10px;color:#16a34a;margin:0 0 5px 4px}
.wai-upload{display:none}
.wai-media-box{display:flex;gap:8px;align-items:center;margin-bottom:7px;background:#fff;border:1px solid #dbe3ec;border-radius:10px;padding:7px}
.wai-media-box input{flex:1;border:0;outline:0;font-size:11px}
.wai-media-send{border:0;background:#16a34a;color:#fff;border-radius:7px;padding:7px 9px;font-size:10px;font-weight:800}
.wai-crm-profile{text-align:center;padding:20px;border-bottom:1px solid #e5e7eb}
.wai-crm-avatar{width:72px;height:72px;margin:auto auto 10px;border-radius:50%;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:25px;font-weight:800}
.wai-crm-name{font-weight:800}.wai-crm-number{font-size:11px;color:#64748b;margin-top:3px}
.wai-section{padding:14px;border-bottom:1px solid #e5e7eb}.wai-title{font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800;margin-bottom:8px}
.wai-info{display:flex;justify-content:space-between;gap:8px;padding:6px 0;font-size:11px}.wai-info span:last-child{font-weight:700;text-align:right}
.wai-tag{display:inline-flex;align-items:center;gap:3px;background:#f1f5f9;border-radius:7px;padding:5px 7px;font-size:9px;font-weight:700;margin:2px}
.wai-tag button{border:0;background:transparent;cursor:pointer}
.wai-preset{border:1px solid #dbe3ec;background:#fff;border-radius:7px;padding:5px 7px;font-size:9px;cursor:pointer;margin:2px}
.wai-note{font-size:10px;color:#94a3b8}
@media(max-width:1100px){.wai-shell{grid-template-columns:280px minmax(0,1fr)}.wai-crm{display:none}}
@media(max-width:800px){.wai-shell{grid-template-columns:1fr;height:auto}.wai-side{height:320px}.wai-main{height:680px}.wai-bubble{max-width:88%}}
</style>

<div
    class="wai-shell"
    x-data="{
        emojiOpen:false,
        recording:false,
        mediaRecorder:null,
        audioChunks:[],
        typing:false,
        quickReplies:[
            'Merhaba 👋 Size nasıl yardımcı olabilirim?',
            'Fiyat bilgisi için hemen yardımcı olabilirim.',
            'Bilgilerinizi aldım. Kısa süre içinde dönüş yapacağız.',
            'Siparişiniz için gerekli bilgileri paylaşabilir misiniz?'
        ],
        insertText(text){
            this.$wire.messageText = this.$wire.messageText
                ? this.$wire.messageText + ' ' + text
                : text;
            this.emojiOpen=false;
        },
        sendOnEnter(e){
            if(e.key==='Enter' && !e.shiftKey){
                e.preventDefault();
                this.$wire.sendMessage();
            }
        },
        async toggleRecord(){
            if(this.recording){
                this.mediaRecorder?.stop();
                return;
            }

            if(!navigator.mediaDevices?.getUserMedia){
                alert('Tarayıcınız ses kaydını desteklemiyor.');
                return;
            }

            const stream = await navigator.mediaDevices.getUserMedia({audio:true});
            this.audioChunks=[];
            this.mediaRecorder=new MediaRecorder(stream);
            this.mediaRecorder.ondataavailable=(e)=>{
                if(e.data.size>0) this.audioChunks.push(e.data);
            };
            this.mediaRecorder.onstop=()=>{
                const blob=new Blob(this.audioChunks,{type:'audio/webm'});
                const reader=new FileReader();
                reader.onloadend=()=>{
                    this.$wire.sendRecordedAudio(reader.result);
                };
                reader.readAsDataURL(blob);
                stream.getTracks().forEach(t=>t.stop());
                this.recording=false;
            };
            this.mediaRecorder.start();
            this.recording=true;
        }
    }"
    wire:poll.4s
>
    <aside class="wai-side">
        <div class="wai-search">
            <input
                wire:model.live.debounce.400ms="search"
                placeholder="İsim, telefon veya firma ara..."
            >
        </div>

        <div class="wai-filters">
            <button class="wai-filter {{ $filter==='all' && $filterTag==='' ? 'active':'' }}" wire:click="setFilter('all')">Tümü</button>
            <button class="wai-filter {{ $filter==='unread' ? 'active':'' }}" wire:click="setFilter('unread')">🔔 Okunmamış</button>
            <button class="wai-filter {{ $filter==='human' ? 'active':'' }}" wire:click="setFilter('human')">👤 İnsan</button>
            <button class="wai-filter {{ $filter==='ai' ? 'active':'' }}" wire:click="setFilter('ai')">🤖 AI</button>

            @foreach (['Yeni Müşteri','Sıcak Müşteri','Teklif Bekliyor','Sipariş','VIP','Acil'] as $tag)
                <button
                    class="wai-filter {{ $filterTag===$tag ? 'active':'' }}"
                    wire:click="setTagFilter(@js($tag))"
                >{{ $tag }}</button>
            @endforeach
        </div>

        <div class="wai-list">
            @forelse($this->conversations as $conversation)
                @php
                    $name = $conversation->customer_name ?: $conversation->whatsapp_number;
                    $initial = mb_strtoupper(mb_substr($name,0,1));
                    $lastAt = $conversation->last_message_at;
                @endphp

                <button
                    class="wai-chat {{ $selectedConversationId === $conversation->id ? 'active':'' }}"
                    wire:click="selectConversation({{ $conversation->id }})"
                >
                    <div class="wai-chat-row">
                        <div class="wai-avatar">{{ $initial }}</div>
                        <div class="wai-chat-body">
                            <div class="wai-chat-head">
                                <span class="wai-name">{{ $name }}</span>
                                <span class="wai-time">{{ $lastAt?->format('H:i') }}</span>
                            </div>
                            <div class="wai-preview">{{ $conversation->last_message_preview }}</div>
                            <div style="margin-top:5px">
                                <span class="wai-badge {{ $conversation->human_takeover ? 'human':'' }}">
                                    {{ $conversation->human_takeover ? '👤 İnsan' : '🤖 AI' }}
                                </span>
                            </div>
                        </div>

                        @if((int)$conversation->unread_count > 0)
                            <span class="wai-unread">{{ $conversation->unread_count }}</span>
                        @endif
                    </div>
                </button>
            @empty
                <div style="padding:30px;text-align:center;color:#94a3b8;font-size:12px">
                    Konuşma bulunamadı.
                </div>
            @endforelse
        </div>
    </aside>

    <main class="wai-main">
        @if($this->selectedConversation)
            @php
                $selectedName = $this->selectedConversation->customer_name ?: $this->selectedConversation->whatsapp_number;
            @endphp

            <header class="wai-header">
                <div>
                    <div class="wai-header-name">{{ $selectedName }}</div>
                    <div class="wai-header-number">{{ $this->selectedConversation->whatsapp_number }}</div>
                </div>

                <div class="wai-actions">
                    @if($this->selectedConversation->human_takeover)
                        <span class="wai-badge human">👤 İnsan Yönetiyor</span>
                        <button class="wai-btn wai-ai" wire:click="releaseToAi">🤖 AI'ye Geri Ver</button>
                    @else
                        <span class="wai-badge">🤖 AI Aktif</span>
                        <button class="wai-btn wai-human" wire:click="takeOver">👤 İnsan Devral</button>
                    @endif
                </div>
            </header>

            <section
                class="wai-messages"
                x-data
                x-init="$nextTick(()=>{$el.scrollTop=$el.scrollHeight})"
                wire:key="messages-{{ $selectedConversationId }}-{{ $this->messages->count() }}"
            >
                @php $lastDate = null; @endphp

                @forelse($this->messages as $message)
                    @php
                        $dateKey = $message->created_at?->format('Y-m-d');
                        $senderType = $message->sender_type ?: ($message->role === 'assistant' ? 'ai' : 'customer');
                        $isCustomer = $senderType === 'customer';
                        $isHuman = $senderType === 'human';
                        $type = $message->message_type ?: 'text';
                        $status = $message->status ?: 'sent';
                    @endphp

                    @if($dateKey !== $lastDate)
                        <div class="wai-date"><span>{{ $message->created_at?->format('d.m.Y') }}</span></div>
                        @php $lastDate = $dateKey; @endphp
                    @endif

                    <div class="wai-row {{ $isCustomer ? 'customer':'outgoing' }}">
                        <div class="wai-bubble {{ $isCustomer ? 'customer' : ($isHuman ? 'human':'ai') }}">
                            <div class="wai-sender">
                                @if($isCustomer) Müşteri
                                @elseif($isHuman) 👤 {{ $message->sentByUser?->name ?? 'Personel' }}
                                @else 🤖 Yapay Zekâ
                                @endif
                            </div>

                            @if($type === 'image' && $message->media_url)
                                <img class="wai-media" src="{{ $message->media_url }}" alt="Fotoğraf">
                            @elseif($type === 'video' && $message->media_url)
                                <video class="wai-media" controls src="{{ $message->media_url }}"></video>
                            @elseif($type === 'audio' && $message->media_url)
                                <audio class="wai-audio" controls src="{{ $message->media_url }}"></audio>
                            @elseif($type === 'document' && $message->media_url)
                                <a class="wai-doc" href="{{ $message->media_url }}" target="_blank" rel="noopener">
                                    📎 <span>{{ $message->media_filename ?: 'Belgeyi aç' }}</span>
                                </a>
                            @endif

                            @if($message->media_caption)
                                <div class="wai-text" style="margin-top:6px">{{ $message->media_caption }}</div>
                            @elseif($type === 'text' || !$message->media_url)
                                <div class="wai-text">{{ $message->message }}</div>
                            @endif

                            <div class="wai-meta">
                                <span>{{ $message->created_at?->format('H:i') }}</span>
                                @if(!$isCustomer)
                                    <span class="wai-check">
                                        @switch($status)
                                            @case('read') ✓✓ @break
                                            @case('delivered') ✓✓ @break
                                            @case('played') ✓✓ @break
                                            @case('error') ⚠ @break
                                            @default ✓✓
                                        @endswitch
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center;color:#64748b;padding:40px">
                        Bu konuşmada henüz mesaj bulunmuyor.
                    </div>
                @endforelse
            </section>

            <form
                class="wai-composer"
                wire:submit="sendMessage"
                x-on:submit="typing=false"
            >
                <div class="wai-tools">
                    <button type="button" class="wai-icon" @click="emojiOpen=!emojiOpen">😊</button>

                    <label class="wai-icon" title="Fotoğraf / video / belge">
                        📎
                        <input
                            class="wai-upload"
                            type="file"
                            wire:model="mediaUpload"
                            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                            @change="$wire.mediaType='media'"
                        >
                    </label>

                    <button type="button" class="wai-icon" @click="toggleRecord()" x-text="recording ? '⏹️' : '🎤'"></button>

                    <div class="wai-emoji" x-show="emojiOpen" x-cloak @click.outside="emojiOpen=false">
                        @foreach(['😀','😂','😍','👍','🙏','❤️','🔥','🎉','📦','💰','😊','👋','👏','🤝','✅','❌','⭐','🚀'] as $emoji)
                            <button type="button" @click="insertText(@js($emoji))">{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>

                @if($mediaUpload)
                    <div class="wai-media-box">
                        <span>📎</span>
                        <input wire:model="mediaCaption" placeholder="Açıklama ekleyin (isteğe bağlı)">
                        <button type="button" class="wai-media-send" wire:click="sendMediaMessage">Gönder</button>
                        <button type="button" class="wai-media-send" style="background:#64748b" wire:click="clearMedia">×</button>
                    </div>
                @endif

                <div class="wai-quick">
                    <span style="font-size:9px;color:#94a3b8;align-self:center">Hızlı</span>
                    <template x-for="reply in quickReplies" :key="reply">
                        <button type="button" @click="insertText(reply)" x-text="reply"></button>
                    </template>
                </div>

                <div class="wai-compose-row">
                    <textarea
                        class="wai-textarea"
                        wire:model="messageText"
                        placeholder="Mesaj yaz..."
                        rows="2"
                        @input="typing=true"
                        @keydown="sendOnEnter($event)"
                    ></textarea>

                    <button class="wai-send" type="submit" wire:loading.attr="disabled" wire:target="sendMessage">
                        <span wire:loading.remove wire:target="sendMessage">Gönder</span>
                        <span wire:loading wire:target="sendMessage">...</span>
                    </button>
                </div>

                <div class="wai-hint">
                    <span x-show="typing" class="wai-typing">✍️ Yazıyor...</span>
                    <span>Enter: Gönder · Shift + Enter: Yeni satır</span>
                </div>

                @if(!$this->selectedConversation->human_takeover)
                    <div style="font-size:9px;color:#64748b;margin-top:4px">
                        Mesaj gönderdiğinizde konuşma otomatik olarak İnsan Yönetiyor moduna geçer.
                    </div>
                @endif
            </form>
        @else
            <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#64748b">
                Bir konuşma seçin.
            </div>
        @endif
    </main>

    @if($this->selectedConversation)
        @php
            $selectedName = $this->selectedConversation->customer_name ?: $this->selectedConversation->whatsapp_number;
            $tags = $this->selectedConversation->etiketler();
            $presets = ['Yeni Müşteri','Sıcak Müşteri','Teklif Bekliyor','Sipariş','VIP','Acil'];
        @endphp

        <aside class="wai-crm">
            <div class="wai-crm-profile">
                <div class="wai-crm-avatar">{{ mb_strtoupper(mb_substr($selectedName,0,1)) }}</div>
                <div class="wai-crm-name">{{ $selectedName }}</div>
                <div class="wai-crm-number">{{ $this->selectedConversation->whatsapp_number }}</div>
            </div>

            <div class="wai-section">
                <div class="wai-title">Müşteri</div>
                <div class="wai-info"><span>Telefon</span><span>{{ $this->selectedConversation->whatsapp_number }}</span></div>
                <div class="wai-info"><span>Firma</span><span>{{ $this->selectedConversation->aiBot?->company_name ?? $this->selectedConversation->aiBot?->name ?? '-' }}</span></div>
                <div class="wai-info"><span>Son aktivite</span><span>{{ $this->selectedConversation->updated_at?->diffForHumans() }}</span></div>
                <div class="wai-info"><span>Okunmamış</span><span>{{ $this->selectedConversation->unread_count }}</span></div>
            </div>

            <div class="wai-section">
                <div class="wai-title">Etiketler</div>

                @foreach($tags as $tag)
                    <span class="wai-tag">
                        {{ $tag }}
                        <button type="button" wire:click="removeTag(@js($tag))">×</button>
                    </span>
                @endforeach

                <div style="margin-top:7px">
                    @foreach($presets as $tag)
                        @if(!in_array($tag,$tags,true))
                            <button type="button" class="wai-preset" wire:click="addTagFromPreset(@js($tag))">+ {{ $tag }}</button>
                        @endif
                    @endforeach
                </div>

                <form wire:submit="addTag" style="display:flex;gap:5px;margin-top:8px">
                    <input wire:model="newTag" maxlength="40" placeholder="Özel etiket..." style="min-width:0;flex:1;border:1px solid #d1d5db;border-radius:7px;padding:7px;font-size:10px">
                    <button type="submit" class="wai-preset">Ekle</button>
                </form>
            </div>

            <div class="wai-section">
                <div class="wai-title">Durum</div>
                <div class="wai-info"><span>Yönetim</span><span>{{ $this->selectedConversation->human_takeover ? '👤 İnsan' : '🤖 AI' }}</span></div>
            </div>

            <div class="wai-section">
                <div class="wai-title">Notlar</div>
                <div class="wai-note">Müşteri not sistemi sonraki CRM aşamasında kalıcı hale getirilebilir.</div>
            </div>
        </aside>
    @endif
</div>
</x-filament-panels::page>