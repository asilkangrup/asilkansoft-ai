<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiBots extends ListRecords
{
    protected static string $resource = AiBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
