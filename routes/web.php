<?php

use App\Http\Controllers\RealEstatePrivateMediaController;
use App\Http\Controllers\TrackedPublicDemoController;
use App\Http\Controllers\WaiLeadDemoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')
    ->name('home');

Route::get('/demo/lead/{token}', [WaiLeadDemoController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:60,1')
    ->name('demo.lead.show');

Route::post('/demo/lead/{token}/whatsapp/connect', [WaiLeadDemoController::class, 'connectWhatsApp'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:10,1')
    ->name('demo.lead.whatsapp.connect');

Route::get('/demo/lead/{token}/whatsapp/status', [WaiLeadDemoController::class, 'whatsappStatus'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:60,1')
    ->name('demo.lead.whatsapp.status');

Route::post('/demo/chat', [TrackedPublicDemoController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('demo.chat');

Route::get('/admin/emlak-medya/{profile}/{media}', [RealEstatePrivateMediaController::class, 'show'])
    ->whereNumber('profile')
    ->whereUuid('media')
    ->name('real-estate.private-media');

Route::get('/admin/emlak-whatsapp-medya/{message}', [RealEstatePrivateMediaController::class, 'showInbound'])
    ->whereNumber('message')
    ->name('real-estate.private-inbound-media');
