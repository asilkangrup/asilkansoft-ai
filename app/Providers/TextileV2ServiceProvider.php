<?php
namespace App\Providers;
use App\Services\TextileV2\TextileV2WhatsAppInboundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class TextileV2ServiceProvider extends ServiceProvider
{
 public function register(): void {}
 public function boot(): void
 {
  Route::post('/api/textile-v2/whatsapp/webhook', function(Request $request, TextileV2WhatsAppInboundService $service){
   $service->process($request->all());
   return response()->json(['ok'=>true]);
  });
  Route::get('/api/textile-v2/health', fn()=>response()->json(['ok'=>true,'bot_id'=>TextileV2WhatsAppInboundService::BOT_ID,'instance'=>TextileV2WhatsAppInboundService::INSTANCE]));
 }
}
