<?php

namespace Tests\Feature;

use App\Jobs\RegisterRealEstateMediaCrmFinding;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstatePrivateMedia;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\EvolutionMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealEstateDurableMediaCrmOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_media_is_persisted_durably_and_survives_missing_local_cache(): void
    {
        Queue::fake();
        Storage::fake('local');
        config()->set('evolution.url', 'https://evolution.example.test');
        config()->set('evolution.api_key', 'test-key');
        Http::fake([
            'https://evolution.example.test/chat/getBase64FromMediaMessage/emlak-ai-35' => Http::response([
                'base64' => base64_encode('durable-private-image'),
            ]),
        ]);

        $user = $this->seedIsolatedAccount();
        $conversation = $this->conversation();
        $message = ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$conversation->session_id,'role'=>'user','sender_type'=>'customer',
            'message'=>'[Fotoğraf]','message_type'=>'image','media_url'=>'temporary-provider-url',
            'media_mime_type'=>'image/jpeg','whatsapp_message_id'=>'durable-photo-1','status'=>'received',
        ]);

        app(EvolutionMediaService::class)->persistPrivateInboundMedia(
            message: $message,
            instanceName: 'emlak-ai-35',
            mediaContext: [
                'type'=>'image','mime_type'=>'image/jpeg',
                'message_envelope'=>['key'=>['id'=>'durable-photo-1']],
            ],
        );

        $durable = RealEstatePrivateMedia::query()->where('chat_message_id', $message->id)->firstOrFail();
        $this->assertSame(40, $durable->user_id);
        $this->assertSame(37, $durable->organization_id);
        $this->assertSame(35, $durable->ai_bot_id);
        $this->assertSame('durable-private-image', base64_decode($durable->content_base64, true));

        $message->refresh();
        $path = substr((string) $message->media_url, strlen('private:'));
        Storage::disk('local')->delete($path);
        Storage::disk('local')->assertMissing($path);

        $response = $this->actingAs($user)
            ->get(route('real-estate.private-inbound-media', ['message'=>$message->id]))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-type', 'image/jpeg');

        $this->assertSame('durable-private-image', $response->getContent());
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_media_observer_never_calls_vision_and_crm_job_registers_photo_without_analysis(): void
    {
        Queue::fake();
        $this->seedIsolatedAccount();
        $conversation = $this->conversation();
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,
            'user_id'=>40,'ai_bot_id'=>35,'profile_type'=>'seller','data'=>[],'valuation'=>[],
        ]));
        $message = ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$conversation->session_id,'role'=>'user','sender_type'=>'customer',
            'message'=>'[Fotoğraf]','message_type'=>'image','media_url'=>'private:real-estate-inbound/1/original.jpg',
            'media_mime_type'=>'image/jpeg','whatsapp_message_id'=>'crm-only-photo','status'=>'received',
        ]);

        Queue::assertPushed(RegisterRealEstateMediaCrmFinding::class);

        (new RegisterRealEstateMediaCrmFinding($message->id))->handle();
        $profile->refresh();
        $finding = data_get($profile->data, 'media_findings.0');

        $this->assertSame('crm-only-photo', $finding['message_id']);
        $this->assertSame('property_photo', $finding['media_category']);
        $this->assertFalse($finding['vision_analyzed']);
        $this->assertFalse($finding['legal_verification']);
        $this->assertSame(0, $finding['confidence_score']);

        $observerSource = file_get_contents(app_path('Observers/ChatMessageObserver.php'));
        $this->assertStringNotContainsString('RealEstateMediaAnalysisService', $observerSource);
        $this->assertStringNotContainsString('->process(', $observerSource);
    }

    private function conversation(): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>'whatsapp:35:durable-media-test',
            'whatsapp_number'=>'905550000001','customer_name'=>'Test',
            'tags'=>[],'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }

    private function seedIsolatedAccount(): User
    {
        $user = User::query()->forceCreate([
            'id'=>40,'name'=>'Emlak AI','email'=>'durable-media@example.test','password'=>Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'durable-media-test',
            'plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active',
        ]);
        $user->organizations()->syncWithoutDetaching([
            37 => ['role'=>'owner','status'=>'active','joined_at'=>now()],
        ]);
        AiBot::query()->forceCreate([
            'id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul',
            'openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate',
            'lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35',
            'follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true,
        ]);

        return $user;
    }
}
