<?php

namespace App\Filament\Resources\OutreachLeads\Pages;

use App\Filament\Resources\OutreachLeads\OutreachLeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutreachLeads extends ListRecords
{
    protected static string $resource = OutreachLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Lead Ekle'),
        ];
    }
}
