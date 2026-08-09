<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        return 'WhatsApp Yapay Zekâ Paneli';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! filament()->hasRegistration()) {
            return new HtmlString(
                '
                <div style="
                    margin-top:8px;
                    text-align:center;
                    line-height:1.7;
                    color:#6b7280;
                    font-size:14px;
                ">
                    <strong style="color:#111827;">
                        30 WhatsApp AI cevabını ücretsiz deneyin.
                    </strong>
                </div>
                '
            );
        }

        $registerUrl = filament()->getRegistrationUrl();

        return new HtmlString(
            '
            <div style="
                margin-top:8px;
                text-align:center;
                line-height:1.7;
                color:#6b7280;
                font-size:14px;
            ">
                <strong style="color:#111827;">
                    30 WhatsApp AI cevabını ücretsiz deneyin.
                </strong>

                <br>

                Hesabınız yok mu?

                <a
                    href="'.$registerUrl.'"
                    style="
                        color:#2563eb;
                        font-weight:800;
                        text-decoration:none;
                    "
                >
                    Ücretsiz Kayıt Ol →
                </a>
            </div>
            '
        );
    }
}