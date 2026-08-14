<?php

namespace App\Filament\Pages;

use App\Models\AiBot;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Kanallar extends Page
{
    protected string $view = 'filament.pages.kanallar';

    protected static ?string $navigationLabel = 'Kanallar';

    protected static ?string $title = 'Kanallar';

    protected static ?string $slug = 'kanallar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|UnitEnum|null $navigationGroup = 'WAI';

    protected static ?int $navigationSort = 20;

    public ?AiBot $bot = null;

    public function mount(): void
    {
        $this->bot = AiBot::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
    }

    public function getWhatsAppConnectedProperty(): bool
    {
        if (! $this->bot) {
            return false;
        }

        return in_array(
            strtolower((string) $this->bot->whatsapp_status),
            [
                'connected',
                'open',
            ],
            true
        );
    }

    public function getConnectedChannelsCountProperty(): int
    {
        return $this->whatsAppConnected
            ? 1
            : 0;
    }

    public function getAvailableChannelsCountProperty(): int
    {
        return 4;
    }

    public function getWhatsAppManageUrlProperty(): string
    {
        if (! $this->bot) {
            return url('/admin/ai-bots/create');
        }

        return url(
            '/admin/ai-bots/'
            .$this->bot->getKey()
            .'/whatsapp'
        );
    }
}