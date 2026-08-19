<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| CRM TAKİP / ÖNCELİKLİ LEAD BİLDİRİMLERİ
|--------------------------------------------------------------------------
*/

Schedule::command(
    'wai:send-follow-up-notifications'
)
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| WAI CRM ALARM MERKEZİ
|--------------------------------------------------------------------------
|
| Kritik CRM sinyallerini sık aralıklarla kontrol eder.
|
*/

Schedule::command(
    'wai:check-crm-alarms'
)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| WAI GÜNLÜK YÖNETİCİ ÖZETİ
|--------------------------------------------------------------------------
*/

Schedule::command(
    'wai:generate-manager-summaries'
)
    ->dailyAt('08:00')
    ->withoutOverlapping();