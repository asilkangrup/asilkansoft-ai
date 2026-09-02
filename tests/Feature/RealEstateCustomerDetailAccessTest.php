<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakCrm;
use App\Filament\Pages\EmlakMusteriDetay;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\RealEstatePrivateMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RealEstateCustomerDetailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_account_can_open_only_its_real_estate_customer_detail(): void
    {
        $user = $this->seedAccount();
        $customer = $this->conversation(40, 37, 35, 'allowed-customer');
        $foreign = $this->conversation(41, 38, 36, 'foreign-customer');

        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $customer->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'location' => 'Marmaris Hisarönü', 'city'=>'Muğla','district'=>'Marmaris','property_type' => 'arsa',
                'area_sqm'=>1200,'block_no'=>'123','parcel_no'=>'45','asking_price'=>4_300_000,
                'title_deed_type'=>'Arsa','zoning_status'=>'Konut','urgency'=>'high',
                'commercial_terms'=>['commission_rate_percent'=>4],
                'seller_offer_packet_intelligence'=>[
                    'status'=>'nearly_ready','missing_critical_for_offer'=>['property_photo'],
                    'missing_supporting_context'=>['listing_reference'],
                ],
                'investor_offer_handoff_intelligence'=>[
                    'ready_for_operator_handoff'=>false,'candidate_count'=>0,
                    'recommended_operator_action'=>'Satıcıdan güncel fotoğrafları iste.',
                ],
                'media_findings' => [
                    ['message_id'=>'photo-1','media_category'=>'property_photo','summary'=>'Arsanın güncel görünümü'],
                    ['message_id'=>'deed-1','media_category'=>'title_deed','summary'=>'Tapu görseli'],
                    ['message_id'=>'listing-1','media_category'=>'listing','summary'=>'İlan ekran görüntüsü'],
                    ['message_id'=>'map-1','media_category'=>'location_map','summary'=>'Konum görseli'],
                ],
            ],
            'valuation' => ['realistic_sale_min'=>3_500_000,'realistic_sale_max'=>3_900_000,'investor_buy_min'=>3_100_000,'investor_buy_max'=>3_120_000,'confidence_score'=>82],
        ]));

        foreach ([
            ['photo-1','image','https://media.example.test/arsa.jpg','image/jpeg'],
            ['deed-1','image','https://media.example.test/tapu.jpg','image/jpeg'],
            ['listing-1','image','https://media.example.test/ilan.jpg','image/jpeg'],
            ['map-1','image','https://media.example.test/konum.jpg','image/jpeg'],
        ] as [$messageId, $type, $url, $mime]) {
            ChatMessage::query()->forceCreate([
                'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
                'session_id'=>$customer->session_id,'role'=>'user','sender_type'=>'customer',
                'message'=>'[Görsel]','message_type'=>$type,'media_url'=>$url,
                'media_mime_type'=>$mime,'whatsapp_message_id'=>$messageId,'status'=>'received',
            ]);
        }

        $this->actingAs($user);
        $this->assertTrue(EmlakMusteriDetay::canAccess());

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $customer->id;
        $this->assertSame($customer->id, $detail->getCustomerProperty()?->id);
        $gallery = $detail->getMediaGalleryProperty();
        $this->assertCount(1, $gallery['Arsa Fotoğrafları']);
        $this->assertCount(1, $gallery['Tapu / Parsel Belgeleri']);
        $this->assertCount(1, $gallery['İlan Görselleri']);
        $this->assertArrayNotHasKey('Konum Görselleri', $gallery);
        $dossier = $detail->getDossierProperty();
        $this->assertSame('123', $dossier['property']['Ada']);
        $this->assertSame('45', $dossier['property']['Parsel']);
        $this->assertNull($dossier['pricing']['Yatırımcı hedef üst']);
        $this->assertNull($dossier['pricing']['Tahmini komisyon']);
        $this->assertNotContains('property_photo', $dossier['missing']);
        $this->assertNotContains('listing_reference', $dossier['missing']);
        $this->assertNotSame('', trim($dossier['call_script']));
        $this->assertCount(4, $detail->getMessagesProperty());
        $legacyCrm = $detail->getLegacyCrmProperty();
        $this->assertMatchesRegularExpression('/^\\d{1,3}\\/100$/', $legacyCrm['Fırsat puanı']);
        $this->assertArrayHasKey('AI CRM özeti', $legacyCrm);
        $this->assertArrayHasKey('Sonraki en iyi aksiyon', $legacyCrm);
        $this->assertArrayHasKey('Tahmini portföy değeri', $legacyCrm);

        $detail->customerId = $foreign->id;
        $this->assertNull($detail->getCustomerProperty());

        $record = (new EmlakCrm)->getRecordsProperty()->first();
        $this->assertStringContainsString('/admin/emlak-musteri-detay?customer='.$customer->id, $record['customer_url']);
        $this->assertStringNotContainsString('/admin/musteriler?', $record['customer_url']);
    }

    public function test_operator_can_upload_private_media_and_record_title_owner_relation(): void
    {
        Storage::fake('local');
        $user = $this->seedAccount();
        $customer = $this->conversation(40, 37, 35, 'manual-media-customer');
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$customer->id,'user_id'=>40,'ai_bot_id'=>35,
            'profile_type'=>'seller','data'=>[],'valuation'=>[],
        ]));

        $this->actingAs($user);
        Livewire::withQueryParams(['customer'=>$customer->id])
            ->test(EmlakMusteriDetay::class)
            ->set('manualMediaCategory', 'title_deed')
            ->set('manualUpload', UploadedFile::fake()->image('tapu.jpg'))
            ->call('uploadManualMedia')
            ->assertHasNoErrors()
            ->set('titleOwnerRelation', 'relative')
            ->set('titleOwnerNote', 'Babasının üzerine')
            ->call('saveTitleOwnership')
            ->assertHasNoErrors();

        $profile->refresh();
        $manual = data_get($profile->data, 'manual_media.0');
        $this->assertSame('title_deed', $manual['category']);
        $this->assertTrue($manual['private']);
        Storage::disk('local')->assertExists($manual['storage_path']);
        $this->assertDatabaseHas('real_estate_private_media', [
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'real_estate_profile_id'=>$profile->id,'media_key'=>$manual['id'],
        ]);
        $this->assertSame('relative', data_get($profile->data, 'title_ownership.relation'));
        $this->assertSame('Babasının üzerine', data_get($profile->data, 'title_ownership.note'));
        $this->assertFalse((bool) data_get($profile->data, 'media_findings.0.legal_verification'));

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $customer->id;
        $detail->titleOwnerRelation = 'relative';
        $this->assertSame(
            'Yakını / akrabası — Babasının üzerine',
            $detail->getDossierProperty()['property']['Tapu kimin üzerine']
        );
        $this->assertSame('Manuel', $detail->getMediaGalleryProperty()['Tapu / Parsel Belgeleri']->first()['source']);

        $privateResponse = $this->get(route('real-estate.private-media', [
            'profile'=>$profile->id,'media'=>$manual['id'],
        ]))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('private', (string) $privateResponse->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $privateResponse->headers->get('cache-control'));

        $whatsappMedia = ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$customer->session_id,'role'=>'user','sender_type'=>'customer',
            'message'=>'[Fotoğraf]','message_type'=>'image','media_url'=>'pending-private-copy',
            'media_mime_type'=>'image/jpeg','whatsapp_message_id'=>'private-photo','status'=>'received',
        ]);
        $privatePath = 'real-estate-inbound/'.$whatsappMedia->id.'/original.jpg';
        Storage::disk('local')->put($privatePath, 'private-image');
        $whatsappMedia->forceFill(['media_url'=>'private:'.$privatePath])->saveQuietly();
        RealEstatePrivateMedia::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'chat_message_id'=>$whatsappMedia->id,'real_estate_profile_id'=>null,
            'media_key'=>null,'mime_type'=>'image/jpeg','size'=>13,
            'content_base64'=>base64_encode('private-image'),
        ]);
        Storage::disk('local')->delete($privatePath);
        $profileData = $profile->data;
        $profileData['property_type'] = 'dubleks daire';
        $profileData['media_findings'][] = [
            'message_id'=>'private-photo','media_category'=>'property_photo','summary'=>'Özel WhatsApp fotoğrafı',
        ];
        $profile->forceFill(['data'=>$profileData])->saveQuietly();

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $customer->id;
        $privateGallery = $detail->getMediaGalleryProperty();
        $this->assertSame(
            route('real-estate.private-inbound-media', ['message'=>$whatsappMedia->id], false),
            $privateGallery['Daire Fotoğrafları']->first()['url']
        );
        $this->get(route('real-estate.private-inbound-media', ['message'=>$whatsappMedia->id]))
            ->assertOk()->assertHeader('x-content-type-options', 'nosniff');

        $foreign = User::query()->findOrFail(41);
        $this->actingAs($foreign)
            ->get(route('real-estate.private-inbound-media', ['message'=>$whatsappMedia->id]))
            ->assertForbidden();
        $this->actingAs($foreign)
            ->get(route('real-estate.private-media', [
                'profile'=>$profile->id,'media'=>$manual['id'],
            ]))->assertForbidden();
    }

    private function conversation(int $user, int $organization, int $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>$user,'organization_id'=>$organization,'ai_bot_id'=>$bot,
            'session_id'=>$session,'whatsapp_number'=>'905550000001','customer_name'=>'Test Müşteri',
            'tags'=>[],'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }

    private function seedAccount(): User
    {
        $user = User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'detail-access@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'detail-access','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        $user->organizations()->syncWithoutDetaching([37 => ['role'=>'owner','status'=>'active','joined_at'=>now()]]);
        AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        User::query()->forceCreate(['id'=>41,'name'=>'Foreign','email'=>'foreign-detail@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>38,'owner_user_id'=>41,'name'=>'Foreign','slug'=>'foreign-detail','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        AiBot::query()->forceCreate(['id'=>36,'user_id'=>41,'name'=>'Foreign','company_name'=>'Foreign','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'foreign-detail','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        return $user;
    }
}
