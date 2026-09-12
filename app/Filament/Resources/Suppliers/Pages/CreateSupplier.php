<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();

        $digits = preg_replace('/\D+/', '', (string) ($data['phone_e164'] ?? '')) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '90'.substr($digits, 1);
        } elseif (str_starts_with($digits, '5')) {
            $digits = '90'.$digits;
        }

        $company = trim((string) ($data['company_name'] ?? ''));
        $data['user_id'] = $user->id;
        $data['phone_e164'] = '+'.$digits;
        $data['source'] = 'supplier';
        $data['status'] = 'ready';
        $data['first_message_text'] = trim((string) ($data['first_message_text'] ?? ''))
            ?: "Merhaba, toptan satış tarafınızla ilgili görüşmek istiyorum.\n\nTürkiye genelinde baskı ve tekstil işletmelerine ürün tedariği sağlıyoruz. Düzenli çalışabileceğimiz üretici/toptancılar arıyoruz.\n\nBayi/toptan fiyat listenizi, minimum sipariş adedinizi ve mevcut ürün seçeneklerinizi paylaşabilir misiniz?";

        return $data;
    }
}
