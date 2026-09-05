<?php

use App\Http\Controllers\WaiSalesOutreachWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/wai-sales/whatsapp/webhook', [
    WaiSalesOutreachWebhookController::class,
    'handle',
]);
