<?php

use App\Http\Controllers\PublicDemoController;
use App\Http\Controllers\RealEstatePrivateMediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WAI PUBLIC ANA SAYFA
|--------------------------------------------------------------------------
|
| wai.asilkansoft.com.tr
|
| Admin panel Filament tarafından /admin altında çalışmaya devam eder.
| WhatsApp webhook veya mevcut production akışlarına müdahale etmez.
|
*/

Route::view('/', 'home')
    ->name('home');


/*
|--------------------------------------------------------------------------
| WAI PUBLIC LIVE DEMO
|--------------------------------------------------------------------------
|
| Ana sayfadaki üyelik gerektirmeyen demo sohbet endpoint'i.
|
| Veritabanına kayıt oluşturmaz.
| Evolution API kullanmaz.
| WhatsApp webhook sisteminden tamamen bağımsızdır.
|
*/

Route::post('/demo/chat', [PublicDemoController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('demo.chat');

// Do not use the generic `auth` redirect middleware here. This application
// has a Filament-specific admin login route rather than a global `login`
// route, so an unauthenticated asset request would otherwise become a 500.
// The controller performs the stricter exact 40/37 authorization check and
// intentionally returns 403 before any profile or file lookup.
Route::get('/admin/emlak-medya/{profile}/{media}', [RealEstatePrivateMediaController::class, 'show'])
    ->whereNumber('profile')
    ->whereUuid('media')
    ->name('real-estate.private-media');

Route::get('/admin/emlak-whatsapp-medya/{message}', [RealEstatePrivateMediaController::class, 'showInbound'])
    ->whereNumber('message')
    ->name('real-estate.private-inbound-media');
