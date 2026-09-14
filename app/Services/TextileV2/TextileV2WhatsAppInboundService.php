<?php
namespace App\Services\TextileV2;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Services\EvolutionMediaService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TextileV2WhatsAppInboundService
{
    public const BOT_ID=55;
    public const USER_ID=48;
    public const INSTANCE='istanbul-tisort-v2-55';
    private const GROUP_JID='120363414072361301@g.us';

    public function __construct(
        private readonly EvolutionMediaService $media,
        private readonly WhatsAppService $wa,
        private readonly TextileV2StateService $stateService,
        private readonly TextileV2PreviewService $preview,
    ) {}

    public function process(array $payload): bool
    {
        if(trim((string)($payload['instance']??''))!==self::INSTANCE) return false;
        $bot=AiBot::query()->whereKey(self::BOT_ID)->where('user_id',self::USER_ID)->where('business_sector','textile_v2')->where('whatsapp_instance',self::INSTANCE)->first();
        if(!$bot) return true;

        $event=strtolower(str_replace(['_','-'],'.',(string)($payload['event']??'')));
        if($event!=='messages.upsert') return true;

        $jid=trim((string)data_get($payload,'data.key.remoteJid',''));
        if($jid===''||str_ends_with($jid,'@g.us')) return true;
        $phone=preg_replace('/\D+/','',explode('@',$jid)[0]??'')??'';
        if($phone==='') return true;
        $text=$this->text($payload);

        if((bool)data_get($payload,'data.key.fromMe',false)){
            if($text!==''){
                $key=$this->outboundKey($phone,$text);
                if(!Cache::store('database')->pull($key,false)){
                    ConversationControl::query()->where('ai_bot_id',self::BOT_ID)->where('whatsapp_number',$phone)->update(['human_takeover'=>true,'updated_at'=>now()]);
                }
            }
            return true;
        }

        $mid=trim((string)data_get($payload,'data.key.id',''));
        if($mid!==''&&!Cache::store('database')->add('textile_v2_in:'.$mid,true,now()->addDay())) return true;

        $conversation=$this->conversation($phone,$payload);
        if((bool)$conversation->human_takeover) return true;

        $key='textile_v2_state:'.self::BOT_ID.':'.$phone;
        $state=$this->stateService->normalize(Cache::store('database')->get($key));
        if($this->stateService->restart($text)) $state=$this->stateService->initial();

        $mediaType=$this->mediaType($payload);
        if(in_array($mediaType,['image','document'],true)){
            try{
                $envelope=data_get($payload,'data',[]);
                $encoded=$this->media->downloadBase64(
                    instanceName:self::INSTANCE,
                    messageEnvelope:is_array($envelope)?$envelope:[],
                );
                if(trim($encoded)!==''){
                    $state['logo_base64']=trim($encoded);
                    $state['logo_received']=true;
                    $state['mockup_sent']=false;
                }
            }catch(Throwable $e){
                Log::warning('TEXTILE V2 MEDIA FAILED',['phone'=>$phone,'message'=>$e->getMessage(),'message_id'=>$mid]);
                $this->send($phone,'Görseli okuyamadım. Logoyu JPG, PNG veya WEBP olarak tekrar gönderebilir misiniz?');
                return true;
            }
        }

        if($text!=='') $state=$this->stateService->parse($state,$text);
        Cache::store('database')->put($key,$state,now()->addHours(48));

        if($this->stateService->asksColors($text)){
            $this->send($phone,"Elbette. Mevcut renk kartelasını birazdan göndereceğim.\n\nOtomatik baskı önizlemesini *siyah* ve *beyaz* tişörtlerde hazırlıyoruz; diğer renkleri Fatih Bey manuel hazırlıyor.");
            return true;
        }

        if(($faq=$this->stateService->faq($text))!==null){
            $this->send($phone,$faq);
            return true;
        }

        if(($state['color']??null)&&!in_array($state['color'],['black','white'],true)){
            $this->send($phone,"Renk talebinizi aldım ✓\n\nOtomatik önizleme şu an *siyah* ve *beyaz* tişörtte hazırlanıyor. Seçtiğiniz rengi Fatih Bey manuel olarak hazırlayacak.");
            $this->notifyGroup($phone,$conversation,$state,'Manuel renk talebi');
            return true;
        }

        if(($state['approved']??false)){
            $this->send($phone,'Teşekkür ederim. Onayınızı aldım ✓ Fatih Bey siparişinizin devamı için sizinle iletişime geçecek.');
            $this->notifyGroup($phone,$conversation,$state,'Müşteri önizlemeyi onayladı');
            return true;
        }

        if($this->stateService->ready($state)){
            try{
                $image=$this->preview->create($state);
                $this->wa->sendImage(self::INSTANCE,$phone,$image,'istanbul-tisort-onizleme.jpg','Baskı önizlemeniz hazırlandı ✓','image/jpeg');
                $state['mockup_sent']=true;
                Cache::store('database')->put($key,$state,now()->addHours(48));
                $labels=array_map(fn($p)=>$this->stateService->label($p),$state['positions']);
                $this->send($phone,"Önizlemeyi hazırladım ✓\n\nÜrün: *{$state['product_label']}*\nRenk: *{$state['color_label']}*\nAdet: *{$state['quantity']}*\nBaskı: *".implode(', ',$labels)."*\n\nGörsel uygunsa *Onaylıyorum* yazabilirsiniz.");
                $this->notifyGroup($phone,$conversation,$state,'Yeni baskı önizlemesi gönderildi');
                return true;
            }catch(Throwable $e){
                Log::error('TEXTILE V2 MOCKUP FAILED',['phone'=>$phone,'message'=>$e->getMessage()]);
                $this->send($phone,'Önizlemeyi hazırlarken teknik bir sorun oluştu. Bilgilerinizi kaybetmedim; Fatih Bey gerekirse buradan devralacak.');
                return true;
            }
        }

        $reply=$this->stateService->next($state,$text);
        if($reply!=='') $this->send($phone,$reply);
        return true;
    }

    private function conversation(string $phone,array $payload): ConversationControl
    {
        $session='whatsapp:v2:'.self::BOT_ID.':'.$phone;
        $c=ConversationControl::firstOrCreate(
            ['ai_bot_id'=>self::BOT_ID,'session_id'=>$session],
            ['user_id'=>self::USER_ID,'whatsapp_number'=>$phone,'customer_name'=>null,'unread_count'=>0,'human_takeover'=>false],
        );
        $push=trim((string)data_get($payload,'data.pushName',''));
        $c->forceFill([
            'whatsapp_number'=>$phone,
            'customer_name'=>trim((string)$c->customer_name)!==''?$c->customer_name:($push!==''?$push:null),
            'last_contact_at'=>now(),
        ])->save();
        return $c;
    }

    private function notifyGroup(string $phone,ConversationControl $c,array $s,string $status): void
    {
        try{
            $name=trim((string)($c->customer_name??''))?:'İsimsiz müşteri';
            $positions=empty($s['positions'])?'-':implode(', ',array_map(fn($p)=>$this->stateService->label($p),$s['positions']));
            $this->wa->sendGroupText(self::INSTANCE,self::GROUP_JID,implode("\n",[
                '🖨️ *İstanbul Tişört V2*','',
                'Durum: *'.$status.'*','Müşteri: *'.$name.'*','Telefon: *+'.$phone.'*',
                'Ürün: *'.($s['product_label']??'-').'*','Renk: *'.($s['color_label']??'-').'*',
                'Adet: *'.($s['quantity']??'-').'*','Baskı: *'.$positions.'*',
            ]));
        }catch(Throwable $e){ Log::warning('TEXTILE V2 GROUP FAILED',['message'=>$e->getMessage()]); }
    }

    private function send(string $phone,string $text): void
    {
        Cache::store('database')->put($this->outboundKey($phone,$text),true,now()->addMinutes(2));
        $this->wa->sendText(self::INSTANCE,$phone,$text);
    }

    private function outboundKey(string $phone,string $text): string
    {
        return 'textile_v2_out:'.sha1(self::INSTANCE.'|'.$phone.'|'.trim($text));
    }

    private function text(array $payload): string
    {
        return trim((string)(
            data_get($payload,'data.message.conversation')
            ??data_get($payload,'data.message.extendedTextMessage.text')
            ??data_get($payload,'data.message.imageMessage.caption')
            ??data_get($payload,'data.message.documentMessage.caption')
            ??''
        ));
    }

    private function mediaType(array $payload): string
    {
        if(data_get($payload,'data.message.imageMessage')) return 'image';
        if(data_get($payload,'data.message.documentMessage')) return 'document';
        return 'text';
    }
}
