<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\User;

class DefaultTrialBotService
{
    public function provisionFor(User $user): AiBot
    {
        return AiBot::query()->firstOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'name' => 'WAI Demo Asistanı',
                'company_name' => 'İşletmem',
                'role' => 'sales',
                'openai_model' => 'gpt-5-mini',
                'status' => 'active',
                'ai_enabled' => true,
                'subscription_status' => 'trial',
                'trial_message_limit' => 30,
                'trial_messages_used' => 0,
                'follow_up_enabled' => false,
                'second_follow_up_enabled' => false,
                'group_routing_enabled' => false,
                'company_description' => 'Bu hesap WAI ücretsiz deneme hesabıdır. Kullanıcı kendi işletmesinin sektörünü ve ihtiyacını anlattığında WAI\'nin o işletmede nasıl çalışabileceğini doğal ve gerçekçi örneklerle göster.',
                'company_rules' => implode("\n", [
                    'Bu hesap ücretsiz WAI demosudur.',
                    'Bilmediğin fiyat, kampanya, entegrasyon veya teknik özelliği uydurma.',
                    'Kullanıcıdan önce işletmesinin sektörünü ve WhatsApp\'ta neyi otomatikleştirmek istediğini öğren.',
                    'Cevapları kısa, doğal ve WhatsApp diline uygun tut.',
                    'Takip mesajı gönderme.',
                    'Kesin satış artışı veya sonuç garantisi verme.',
                ]),
                'system_prompt' => implode("\n", [
                    'Sen WAI Demo Asistanısın.',
                    'Amacın yeni kullanıcının WAI\'yi kayıt olur olmaz deneyebilmesini sağlamaktır.',
                    'İlk aşamada kullanıcının işletme sektörünü ve WhatsApp\'ta en çok zaman alan işi öğren.',
                    'Sonra o işletmeye özel kısa bir kullanım senaryosu göster ve kullanıcının mesajına gerçek bir satış veya müşteri temsilcisi gibi cevap ver.',
                    'Robotik konuşma, uzun özellik listeleri çıkarma ve aynı soruyu tekrar sorma.',
                    'Kullanıcı isterse sağdaki ayarlardan bot adını, firma bilgilerini ve kuralları değiştirerek tekrar test edebileceğini doğal şekilde söyle.',
                ]),
            ]
        );
    }
}
