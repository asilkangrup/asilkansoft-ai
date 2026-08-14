<?php

namespace App\Filament\Pages;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Order;
use App\Models\Product;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Schema;

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

    /*
    |--------------------------------------------------------------------------
    | KULLANICI
    |--------------------------------------------------------------------------
    */

    public function getUserName(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'Hoş geldiniz';
        }

        return $user->name ?: 'Hoş geldiniz';
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF / SON BOT
    |--------------------------------------------------------------------------
    */

    public function getBot(): ?AiBot
    {
        if (! auth()->check()) {
            return null;
        }

        return AiBot::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AİT BOT ID'LERİ
    |--------------------------------------------------------------------------
    */

    protected function getUserBotIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return AiBot::query()
            ->where('user_id', auth()->id())
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | TOPLAM MÜŞTERİ / GÖRÜŞME
    |--------------------------------------------------------------------------
    */

    public function getConversationCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $query = ConversationControl::query();

        /*
        |--------------------------------------------------------------------------
        | Öncelik user_id
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                (new ConversationControl())->getTable(),
                'user_id'
            )
        ) {
            return $query
                ->where('user_id', auth()->id())
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Eski / farklı şema için ai_bot_id desteği
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                (new ConversationControl())->getTable(),
                'ai_bot_id'
            )
        ) {
            $botIds = $this->getUserBotIds();

            if (empty($botIds)) {
                return 0;
            }

            return $query
                ->whereIn('ai_bot_id', $botIds)
                ->count();
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | OKUNMAMIŞ MESAJ
    |--------------------------------------------------------------------------
    */

    public function getUnreadCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $table = (new ConversationControl())->getTable();

        if (! Schema::hasColumn($table, 'unread_count')) {
            return 0;
        }

        $query = ConversationControl::query();

        if (
            Schema::hasColumn(
                $table,
                'user_id'
            )
        ) {
            return (int) $query
                ->where('user_id', auth()->id())
                ->sum('unread_count');
        }

        if (
            Schema::hasColumn(
                $table,
                'ai_bot_id'
            )
        ) {
            $botIds = $this->getUserBotIds();

            if (empty($botIds)) {
                return 0;
            }

            return (int) $query
                ->whereIn('ai_bot_id', $botIds)
                ->sum('unread_count');
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | ÜRÜN / HİZMET SAYISI
    |--------------------------------------------------------------------------
    |
    | Product modelimiz user_id kullanmıyor.
    | Ürünler ai_bot_id üzerinden yapay zekâ botuna bağlı.
    |
    */

    public function getProductCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $table = (new Product())->getTable();

        /*
        |--------------------------------------------------------------------------
        | Mevcut doğru yapı: ai_bot_id
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                $table,
                'ai_bot_id'
            )
        ) {
            $botIds = $this->getUserBotIds();

            if (empty($botIds)) {
                return 0;
            }

            return Product::query()
                ->whereIn('ai_bot_id', $botIds)
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | İleriye dönük user_id desteği
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                $table,
                'user_id'
            )
        ) {
            return Product::query()
                ->where('user_id', auth()->id())
                ->count();
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | SİPARİŞ SAYISI
    |--------------------------------------------------------------------------
    |
    | Production ve local şemalar arasında fark varsa 500 vermesin.
    | Önce user_id, yoksa ai_bot_id kullanır.
    |
    */

    public function getOrderCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $table = (new Order())->getTable();

        /*
        |--------------------------------------------------------------------------
        | user_id varsa
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                $table,
                'user_id'
            )
        ) {
            return Order::query()
                ->where('user_id', auth()->id())
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | ai_bot_id varsa
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                $table,
                'ai_bot_id'
            )
        ) {
            $botIds = $this->getUserBotIds();

            if (empty($botIds)) {
                return 0;
            }

            return Order::query()
                ->whereIn('ai_bot_id', $botIds)
                ->count();
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP BAĞLANTI DURUMU
    |--------------------------------------------------------------------------
    */

    public function getWhatsAppConnected(): bool
    {
        $bot = $this->getBot();

        if (! $bot) {
            return false;
        }

        $status = strtolower(
            trim(
                (string) $bot->whatsapp_status
            )
        );

        return in_array(
            $status,
            [
                'connected',
                'open',
                'ready',
                'active',
                'online',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF KANAL SAYISI
    |--------------------------------------------------------------------------
    */

    public function getConnectedChannelCount(): int
    {
        $count = 0;

        if ($this->getWhatsAppConnected()) {
            $count++;
        }

        /*
        |--------------------------------------------------------------------------
        | Instagram / Facebook / Web bağlandığında buraya eklenecek.
        |--------------------------------------------------------------------------
        */

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | URL'LER
    |--------------------------------------------------------------------------
    */

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
            . $bot->getKey()
            . '/edit'
        );
    }
}