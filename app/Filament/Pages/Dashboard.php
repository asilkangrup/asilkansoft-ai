<?php

namespace App\Filament\Pages;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrganizationAccessService;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Schema;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function currentOrganization()
    {
        return $this
            ->accessService()
            ->currentOrganization();
    }

    protected function currentRole(): ?string
    {
        return $this
            ->accessService()
            ->currentRole();
    }

    protected function ownerUserId(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        if ($user->is_admin) {
            return $user->id;
        }

        return (int) (
            $this->currentOrganization()?->owner_user_id
            ?? $user->id
        );
    }

    protected function conversationQuery()
    {
        $user = auth()->user();

        $query =
            ConversationControl::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        $organization =
            $this->currentOrganization();

        if (! $organization) {
            return $query->where(
                'user_id',
                $user->id
            );
        }

        $query->where(
            'organization_id',
            $organization->id
        );

        if ($this->currentRole() === 'sales') {
            $query->where(
                'assigned_user_id',
                $user->id
            );
        }

        return $query;
    }

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
            return 'HoÅŸ geldiniz';
        }

        return $user->name ?: 'HoÅŸ geldiniz';
    }

    /*
    |--------------------------------------------------------------------------
    | AKTÄ°F / SON BOT
    |--------------------------------------------------------------------------
    */

    public function getBot(): ?AiBot
    {
        if (! auth()->check()) {
            return null;
        }

        return AiBot::query()
            ->where(
                'user_id',
                $this->ownerUserId()
            )
            ->latest('id')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AÄ°T BOT ID'LERÄ°
    |--------------------------------------------------------------------------
    */

    protected function getUserBotIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return AiBot::query()
            ->where(
                'user_id',
                $this->ownerUserId()
            )
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | TOPLAM MÃœÅTERÄ° / GÃ–RÃœÅME
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
        | Ã–ncelik user_id
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                (new ConversationControl())->getTable(),
                'user_id'
            )
        ) {
            return $this
                ->conversationQuery()
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Eski / farklÄ± ÅŸema iÃ§in ai_bot_id desteÄŸi
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
    | OKUNMAMIÅ MESAJ
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
            return (int) $this
                ->conversationQuery()
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
    | ÃœRÃœN / HÄ°ZMET SAYISI
    |--------------------------------------------------------------------------
    |
    | Product modelimiz user_id kullanmÄ±yor.
    | ÃœrÃ¼nler ai_bot_id Ã¼zerinden yapay zekÃ¢ botuna baÄŸlÄ±.
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
        | Mevcut doÄŸru yapÄ±: ai_bot_id
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
        | Ä°leriye dÃ¶nÃ¼k user_id desteÄŸi
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                $table,
                'user_id'
            )
        ) {
            return Product::query()
                ->where('user_id', $this->ownerUserId())
                ->count();
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | SÄ°PARÄ°Å SAYISI
    |--------------------------------------------------------------------------
    |
    | Production ve local ÅŸemalar arasÄ±nda fark varsa 500 vermesin.
    | Ã–nce user_id, yoksa ai_bot_id kullanÄ±r.
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
                ->where('user_id', $this->ownerUserId())
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
    | WHATSAPP BAÄLANTI DURUMU
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
    | AKTÄ°F KANAL SAYISI
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
        | Instagram / Facebook / Web baÄŸlandÄ±ÄŸÄ±nda buraya eklenecek.
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
PS C:\laragon\www\asilkansoft-ai>