<?php

namespace App\Http\Middleware;

use App\Filament\Pages\KurulumMerkezi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIncompleteSetup
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | GİRİŞ YAPILMAMIŞSA DEVAM ET
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | ADMINLERİ YÖNLENDİRME
        |--------------------------------------------------------------------------
        |
        | Admin normal dashboard'a gider.
        |
        */

        if ($user->is_admin) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | SADECE PANEL ANA SAYFASINDA KONTROL ET
        |--------------------------------------------------------------------------
        |
        | Böylece müşteri Bot Oluştur, Ürün Ekle veya WhatsApp Bağla
        | sayfalarına girdiğinde tekrar Kurulum Merkezi'ne atılmaz.
        |
        */

        if (! $request->is('admin')) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİNİN YAPAY ZEKÂ BOTUNU BUL
        |--------------------------------------------------------------------------
        */

        $bot = $user->aiBots()
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | KURULUM ADIMLARINI KONTROL ET
        |--------------------------------------------------------------------------
        */

        $botCreated = (bool) $bot;

        $companyCompleted = $bot
            && filled($bot->company_name)
            && filled($bot->company_description);

        $whatsappConnected = $bot
            && $bot->whatsapp_status === 'connected';

        $productsAdded = $bot
            && $bot->products()->exists();

        $followUpConfigured = $bot
            && $bot->follow_up_enabled
            && filled($bot->first_follow_up_minutes)
            && filled($bot->first_follow_up_message);

        /*
        |--------------------------------------------------------------------------
        | KURULUM TAMAMLANDI MI?
        |--------------------------------------------------------------------------
        */

        $setupCompleted =
            $botCreated
            && $companyCompleted
            && $whatsappConnected
            && $productsAdded
            && $followUpConfigured;

        /*
        |--------------------------------------------------------------------------
        | EKSİKSE KURULUM MERKEZİNE GÖNDER
        |--------------------------------------------------------------------------
        */

        if (! $setupCompleted) {
            return redirect()->to(
                KurulumMerkezi::getUrl()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TAMAMSA NORMAL DASHBOARD'A DEVAM
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }
}