<?php

use App\Http\Controllers\RealEstatePrivateMediaController;
use App\Http\Controllers\TrackedPublicDemoController;
use App\Http\Controllers\WaiLeadDemoController;
use App\Models\OutreachLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')
    ->name('home');

Route::view('/sigorta-demo', 'insurance-demo')
    ->middleware('throttle:120,1')
    ->name('insurance.demo');

Route::view('/sigorta', 'insurance-public-demo')
    ->middleware('throttle:120,1')
    ->name('insurance.public.demo');

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

Route::get('/admin/outreach-leads/{lead}/whatsapp', function (Request $request, OutreachLead $lead) {
    $user = $request->user();

    abort_unless($user, 403);
    abort_unless((bool) $user->is_admin || $lead->user_id === $user->id, 403);

    if ($lead->contact_opened_at) {
        return redirect('/admin/isletme-leadleri');
    }

    $lead->forceFill([
        'contact_opened_at' => now(),
        'status' => 'opened',
    ])->save();

    return redirect()->away($lead->whatsappUrl());
})
    ->middleware('auth')
    ->whereNumber('lead')
    ->name('outreach-leads.whatsapp');
