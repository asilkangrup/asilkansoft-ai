@php
    $user = \Filament\Facades\Filament::auth()->user();

    $bot = null;

    $insuranceOnly = $user
        && app(\App\Support\InsuranceTenantContext::class)->isInsuranceOnly($user);

    if ($user && ! $insuranceOnly) {
        $bot = \App\Models\AiBot::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    $showTrialBar =
        $bot
        && $bot->whatsapp_status === 'connected';

    $subscriptionStatus =
        $bot?->subscription_status
        ?: 'trial';

    $trialMessageLimit =
        (int) (
            $bot?->trial_message_limit
            ?? 30
        );

    $trialMessagesUsed =
        (int) (
            $bot?->trial_messages_used
            ?? 0
        );

    $trialMessagesRemaining =
        max(
            0,
            $trialMessageLimit
            -
            $trialMessagesUsed
        );

    $trialProgress =
        $trialMessageLimit > 0
            ? (int) min(
                100,
                round(
                    (
                        $trialMessagesUsed
                        /
                        $trialMessageLimit
                    )
                    * 100
                )
            )
            : 0;

    $trialCompleted =
        $bot
        && (
            $subscriptionStatus === 'expired'
            ||
            (
                $subscriptionStatus === 'trial'
                &&
                $trialMessagesUsed
                >=
                $trialMessageLimit
            )
        );

    $subscriptionActive =
        $bot
        && $subscriptionStatus === 'active'
        && $bot->whatsappAiKullanilabilirMi();
@endphp


@if ($showTrialBar)

    <style>
        .global-trial-bar {
            width: 100%;
            margin-bottom: 18px;
            padding: 16px 18px;
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            background: #eff6ff;
            box-shadow: 0 5px 18px rgba(15,23,42,.05);
        }

        .global-trial-bar.expired {
            border-color: #fecaca;
            background: #fff1f2;
        }

        .global-trial-bar.active {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .global-trial-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .global-trial-left {
            min-width: 0;
        }

        .global-trial-title {
            color: #111827;
            font-size: 14px;
            font-weight: 800;
        }

        .global-trial-text {
            margin-top: 3px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        .global-trial-count {
            flex-shrink: 0;
            text-align: right;
        }

        .global-trial-count strong {
            display: block;
            color: #111827;
            font-size: 20px;
            font-weight: 800;
        }

        .global-trial-count span {
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
        }

        .global-trial-progress {
            height: 8px;
            margin-top: 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #dbeafe;
        }

        .global-trial-progress-bar {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(
                90deg,
                #3b82f6,
                #2563eb
            );
        }

        .global-trial-bar.expired .global-trial-progress {
            background: #fee2e2;
        }

        .global-trial-bar.expired .global-trial-progress-bar {
            background: linear-gradient(
                90deg,
                #f97316,
                #ef4444
            );
        }

        .global-trial-bar.active .global-trial-progress {
            background: #dcfce7;
        }

        .global-trial-bar.active .global-trial-progress-bar {
            background: linear-gradient(
                90deg,
                #22c55e,
                #16a34a
            );
        }

        .global-trial-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 10px;
        }

        .global-trial-remaining {
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
        }

        .global-trial-bar.expired .global-trial-remaining {
            color: #dc2626;
        }

        .global-trial-bar.active .global-trial-remaining {
            color: #16a34a;
        }

        .global-buy-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            background: #dc2626;
            color: #fff !important;
            text-decoration: none !important;
            font-size: 12px;
            font-weight: 800;
        }

        @media (max-width: 700px) {
            .global-trial-top,
            .global-trial-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .global-trial-count {
                text-align: left;
            }

            .global-buy-button {
                width: 100%;
            }
        }
    </style>


    <div
        class="
            global-trial-bar
            {{ $trialCompleted ? 'expired' : '' }}
            {{ $subscriptionActive ? 'active' : '' }}
        "
    >

        <div class="global-trial-top">

            <div class="global-trial-left">

                <div class="global-trial-title">

                    @if ($subscriptionActive)

                        ✓ Paketiniz Aktif

                    @elseif ($trialCompleted)

                        ⚠ Ücretsiz Denemeniz Sona Erdi

                    @else

                        🎁 Ücretsiz Deneme

                    @endif

                </div>

                <div class="global-trial-text">

                    @if ($subscriptionActive)

                        Yapay zekânız WhatsApp üzerinden aktif olarak çalışıyor.

                    @elseif ($trialCompleted)

                        30 ücretsiz WhatsApp AI cevabınız tamamlandı.

                    @else

                        WhatsApp yapay zekânızı ücretsiz kullanıyorsunuz.

                    @endif

                </div>

            </div>


            <div class="global-trial-count">

                @if ($subscriptionActive)

                    <strong>
                        Aktif
                    </strong>

                    <span>
                        WhatsApp AI
                    </span>

                @else

                    <strong>
                        {{ $trialMessagesUsed }} / {{ $trialMessageLimit }}
                    </strong>

                    <span>
                        AI cevabı kullanıldı
                    </span>

                @endif

            </div>

        </div>


        @if (! $subscriptionActive)

            <div class="global-trial-progress">

                <div
                    class="global-trial-progress-bar"
                    style="width: {{ $trialProgress }}%;"
                ></div>

            </div>

        @endif


        <div class="global-trial-footer">

            <div class="global-trial-remaining">

                @if ($subscriptionActive)

                    Yapay zekânız kullanıma açık.

                @elseif ($trialCompleted)

                    Kullanıma devam etmek için paketinizi aktifleştirin.

                @else

                    {{ $trialMessagesRemaining }} ücretsiz AI cevabı kaldı

                @endif

            </div>


            @if ($trialCompleted)

                <a
                    href="https://wa.me/905382399098?text={{ urlencode('Merhaba, WhatsApp Yapay Zeka paketini satın almak istiyorum. Paketler hakkında bilgi alabilir miyim?') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="global-buy-button"
                >
                    Paketi Satın Al →
                </a>

            @endif

        </div>

    </div>

@endif