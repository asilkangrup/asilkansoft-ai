<?php

namespace App\Http\Middleware;

use App\Filament\Pages\KurulumMerkezi;
use App\Support\InsuranceTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIncompleteSetup
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
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
        | ADMİNLERİ HİÇ YÖNLENDİRME
        |--------------------------------------------------------------------------
        */

        if ($user->is_admin) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | BAĞIMSIZ SİGORTA ÜRÜNÜ
        |--------------------------------------------------------------------------
        |
        | Yalnız sigorta ürünü kullanan tenantlar genel WAI kurulumuna veya
        | başka ürün ekranlarına düşmez. Sigorta route'ları ve çıkış işlemi
        | normal şekilde devam eder; diğer panel route'ları operasyon merkezine
        | yönlendirilir.
        |
        */

        if (app(InsuranceTenantContext::class)->isInsuranceOnly($user)) {
            if (
                $request->is('admin/sigorta-*')
                || $request->is('admin/ai-bots/*/whatsapp')
                || $request->is('admin/logout')
            ) {
                return $next($request);
            }

            return redirect()->to('/admin/sigorta-operasyon');
        }

        /*
        |--------------------------------------------------------------------------
        | SADECE PANEL ANA SAYFASINDA ÇALIŞ
        |--------------------------------------------------------------------------
        |
        | Bot, ürün, sipariş, test sohbeti gibi sayfalara dokunmuyoruz.
        |
        */

        if (! $request->is('admin')) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | BU OTURUMDA YÖNLENDİRME ZATEN YAPILDIYSA PANELİ AÇ
        |--------------------------------------------------------------------------
        |
        | Böylece müşteri Kurulum Merkezi'ne gönderildikten sonra
        | sol menüden "Panel"e bastığında tekrar yönlendirilmez.
        |
        */

        $sessionKey =
            'setup_redirect_done_user_'.$user->id;

        if (
            $request->session()->get(
                $sessionKey,
                false
            )
        ) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİNİN SON BOTUNU BUL
        |--------------------------------------------------------------------------
        */

        $bot = $user->aiBots()
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | TEMEL KURULUM DURUMU
        |--------------------------------------------------------------------------
        |
        | Ürün ve otomatik takip zorunlu değil.
        |
        | Temel kurulum:
        | - Bot oluşturuldu
        | - Firma bilgileri tamam
        | - WhatsApp bağlı
        |
        */

        $botCreated =
            (bool) $bot;

        $companyCompleted =
            $bot
            && filled($bot->company_name)
            && filled($bot->company_description);

        $whatsappConnected =
            $bot
            && $bot->whatsapp_status === 'connected';

        $setupCompleted =
            $botCreated
            && $companyCompleted
            && $whatsappConnected;

        /*
        |--------------------------------------------------------------------------
        | BU OTURUM İÇİN İLK KONTROL TAMAMLANDI
        |--------------------------------------------------------------------------
        |
        | Bunu redirect'ten ÖNCE kaydediyoruz.
        |
        */

        $request->session()->put(
            $sessionKey,
            true
        );

        /*
        |--------------------------------------------------------------------------
        | KURULUM EKSİKSE İLK GİRİŞTE KURULUM MERKEZİNE GÖNDER
        |--------------------------------------------------------------------------
        */

        if (! $setupCompleted) {
            return redirect()->to(
                KurulumMerkezi::getUrl()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | KURULUM TAMAMSA NORMAL PANEL
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }
}