<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WAI PUBLIC ANA SAYFA
|--------------------------------------------------------------------------
|
| wai.asilkansoft.com.tr
| Public landing page.
|
| Admin panel Filament tarafından /admin altında çalışmaya devam eder.
| Bu route admin, webhook veya WhatsApp sistemine müdahale etmez.
|
*/

Route::view('/', 'home')->name('home');