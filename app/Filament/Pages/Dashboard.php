<?php

namespace App\Filament\Pages;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Order;
use App\Models\Product;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function getUserName(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'Hoş geldiniz';
        }

        return $user->name ?: 'Hoş geldiniz';
    }

    public function getBot(): ?AiBot
    {
        return AiBot::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
    }

    public function getConversationCount(): int
    {
        return ConversationControl::query()
            ->where('user_id', auth()->id())
            ->count();
    }

    public function getUnreadCount(): int
    {
        return (int) ConversationControl::query()
            ->where('user_id', auth()->id())
            ->sum('unread_count');
    }

    public function getProductCount(): int
    {
        return Product::query()
            ->where('user_id', auth()->id())
            ->count();
    }

    public function getOrderCount(): int
    {
        return Order::query()
            ->where('user_id', auth()->id())
            ->count();
    }

    public function getWhatsAppConnected(): bool
    {
        $bot = $this->getBot();

        if (! $bot) {
            return false;
        }

        return in_array(
            strtolower((string) $bot->whatsapp_status),
            [
                'connected',
                'open',
            ],
            true
        );
    }

    public function getConnectedChannelCount(): int
    {
        return $this->getWhatsAppConnected()
            ? 1
            : 0;
    }

    public function getChannelUrl(): string
    {
        return url('/admin/kanallar');
    }

    public function getInboxUrl(): string
    {
        return url('/admin/conversation-controls');
    }

    public function getBotUrl(): string
    {
        $bot = $this->getBot();

        if (! $bot) {
            return url('/admin/ai-bots/create');
        }

        return url(
            '/admin/ai-bots/'
            .$bot->getKey()
            .'/edit'
        );
    }
}