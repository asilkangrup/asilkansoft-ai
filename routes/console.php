<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| OTOMATİK WHATSAPP TAKİP MESAJLARI
|--------------------------------------------------------------------------
|
| Her 5 dakikada bir zamanı gelen takip mesajlarını kontrol eder.
| Gerçek gönderim zamanı müşterinin panelde seçtiği süreye göre belirlenir.
|
*/

Schedule::command('app:send-conversation-follow-ups')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| WAI CRM TAKİP BİLDİRİMLERİ
|--------------------------------------------------------------------------
|
| CRM içerisinde takip zamanı gelen müşterileri her dakika kontrol eder.
| Zamanı gelen görev için ilgili kullanıcıya Filament database notification
| gönderilir.
|
| Command kendi içinde aynı takip tarihi için tekrar bildirim oluşmasını
| engellediği için scheduler her dakika güvenle çalışabilir.
|
*/

Schedule::command('wai:send-follow-up-notifications')
    ->everyMinute()
    ->withoutOverlapping();