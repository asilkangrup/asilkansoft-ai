<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    /*
    |--------------------------------------------------------------------------
    | SAYFA BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function getHeading(): string
    {
        return 'WhatsApp Yapay Zekâ Paneli';
    }

    /*
    |--------------------------------------------------------------------------
    | ALT BAŞLIK
    |--------------------------------------------------------------------------
    */

    public function getSubheading(): string|HtmlString|null
    {
        return new HtmlString(
            '
            <div style="
                margin-top: 8px;
                text-align: center;
                line-height: 1.7;
                color: #6b7280;
                font-size: 14px;
            ">
                <strong style="color:#111827;">
                    30 WhatsApp AI cevabını ücretsiz deneyin.
                </strong>
                <br>
                Hesabınıza giriş yapın veya ücretsiz kayıt olarak
                yapay zekânızı birkaç dakika içinde kurmaya başlayın.
            </div>
            '
        );
    }
}