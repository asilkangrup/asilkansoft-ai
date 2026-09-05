<?php

namespace App\Filament\Resources\OutreachLeads\Pages;

use App\Filament\Resources\OutreachLeads\OutreachLeadResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOutreachLead extends CreateRecord
{
    protected static string $resource = OutreachLeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();

        $digits = preg_replace('/\D+/', '', (string) ($data['phone_e164'] ?? '')) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '90'.substr($digits, 1);
        } elseif (str_starts_with($digits, '5')) {
            $digits = '90'.$digits;
        }

        $data['user_id'] = $user->id;
        $data['phone_e164'] = '+'.$digits;
        $data['status'] = 'ready';
        $data['first_message_text'] = trim((string) ($data['first_message_text'] ?? ''))
            ?: 'Merhaba kolay gelsin, '.trim((string) $data['company_name']).' doğru mudur?';

        return $data;
    }
}
