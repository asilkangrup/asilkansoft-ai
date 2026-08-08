<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiBot extends CreateRecord
{
    protected static string $resource = AiBotResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}