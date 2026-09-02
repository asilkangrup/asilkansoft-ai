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

Route::get('/admin/emlak-medya/{profile}/{media}', [RealEstatePrivateMediaController::class, 'show'])
    ->whereNumber('profile')
    ->whereUuid('media')
    ->middleware('auth')
    ->name('real-estate.private-media');
