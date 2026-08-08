<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\AiBot;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class KurulumMerkezi extends Page
{
    protected string $view = 'filament.pages.kurulum-merkezi';

    protected static ?string $navigationLabel = 'Kurulum Merkezi';

    protected static ?string $title = 'Kurulum Merkezi';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        $user = Filament::auth()->user();

        $bot = AiBot::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $botCreated = (bool) $bot;

        $companyCompleted = $bot
            && filled($bot->company_name)
            && filled($bot->company_description);

        $whatsappConnected = $bot
            && $bot->whatsapp_status === 'connected';

        $productsAdded = $bot
            ? Product::query()
                ->where('ai_bot_id', $bot->id)
                ->exists()
            : false;

        $followUpConfigured = $bot
            && $bot->follow_up_enabled
            && filled($bot->first_follow_up_minutes)
            && filled($bot->first_follow_up_message);

        $steps = [
            [
                'title' => 'Yapay Zekânı Oluştur',
                'description' => 'İlk yapay zekâ botunu oluştur ve temel rolünü belirle.',
                'completed' => $botCreated,
                'url' => $bot
                    ? AiBotResource::getUrl('edit', ['record' => $bot])
                    : AiBotResource::getUrl('create'),
                'button' => $botCreated ? 'Yapay Zekâyı Düzenle' : 'Yapay Zekâ Oluştur',
                'icon' => '🤖',
            ],
            [
                'title' => 'Firma Bilgilerini Tamamla',
                'description' => 'Firma açıklaması, çalışma saatleri, ödeme ve özel kuralları gir.',
                'completed' => $companyCompleted,
                'url' => $bot
                    ? AiBotResource::getUrl('edit', ['record' => $bot])
                    : AiBotResource::getUrl('create'),
                'button' => 'Firma Bilgilerini Düzenle',
                'icon' => '🏢',
            ],
            [
                'title' => 'WhatsApp Bağlantısını Kur',
                'description' => 'WhatsApp hesabını bağlayarak yapay zekâyı canlı kullanıma aç.',
                'completed' => $whatsappConnected,
                'url' => $bot
                    ? AiBotResource::getUrl('whatsapp', ['record' => $bot])
                    : AiBotResource::getUrl('create'),
                'button' => $whatsappConnected ? 'WhatsApp Durumunu Gör' : 'WhatsApp Bağla',
                'icon' => '💬',
            ],
            [
                'title' => 'Ürünlerini Ekle',
                'description' => 'Yapay zekânın müşterilere önereceği ürün ve hizmetleri ekle.',
                'completed' => $productsAdded,
                'url' => ProductResource::getUrl('create'),
                'button' => 'Ürün Ekle',
                'icon' => '📦',
            ],
            [
                'title' => 'Otomatik Takibi Ayarla',
                'description' => 'Cevap vermeyen müşterilere otomatik hatırlatma mesajları gönder.',
                'completed' => $followUpConfigured,
                'url' => $bot
                    ? AiBotResource::getUrl('edit', ['record' => $bot])
                    : AiBotResource::getUrl('create'),
                'button' => 'Takip Ayarlarını Düzenle',
                'icon' => '⏱️',
            ],
        ];

        $completedCount = collect($steps)
            ->where('completed', true)
            ->count();

        $progress = (int) round(
            ($completedCount / count($steps)) * 100
        );

        return [
            'user' => $user,
            'bot' => $bot,
            'steps' => $steps,
            'progress' => $progress,
            'completedCount' => $completedCount,
            'totalSteps' => count($steps),
        ];
    }
}
