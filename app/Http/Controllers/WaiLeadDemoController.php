<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Services\WaiLeadDemoService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class WaiLeadDemoController extends Controller
{
    public function show(string $token, WaiLeadDemoService $service): View
    {
        $lead = $service->get($token);

        if (! is_array($lead)) {
            throw new NotFoundHttpException('Demo bağlantısının süresi dolmuş veya bağlantı geçersiz.');
        }

        $service->markOpened($token);

        return view('wai-lead-demo', [
            'token' => $token,
            'lead' => $lead,
        ]);
    }

    public function connectWhatsApp(
        string $token,
        WaiLeadDemoService $demoService,
        WhatsAppService $whatsAppService
    ): JsonResponse {
        $lead = $demoService->get($token);

        if (! is_array($lead)) {
            return response()->json([
                'success' => false,
                'message' => 'Demo bağlantısının süresi dolmuş.',
            ], 404);
        }

        try {
            $cacheKey = 'wai_lead_demo_bot:'.$token;
            $botId = Cache::get($cacheKey);
            $bot = $botId ? AiBot::query()->find($botId) : null;

            if (! $bot) {
                $company = trim((string) ($lead['company_name'] ?? 'Demo İşletme'));
                $description = trim((string) ($lead['company_description'] ?? ''));
                $instanceName = 'wai-demo-'.Str::lower(Str::random(12));

                $bot = AiBot::query()->create([
                    'user_id' => 43,
                    'name' => 'Demo - '.mb_substr($company, 0, 80),
                    'company_name' => $company,
                    'company_description' => $description,
                    'company_rules' => 'Kısa, doğal ve müşterinin sektörüne uygun konuş. Bilmediğin bilgiyi uydurma. Takip mesajı gönderme.',
                    'system_prompt' => "Sen {$company} için hazırlanmış canlı WhatsApp demo yapay zekasısın. Kullanıcı WAI ürününü değil, bu işletmenin müşterisiymiş gibi konuşacaktır. İşletme bağlamına göre kısa, doğal ve gerçek bir müşteri temsilcisi gibi cevap ver.\n\nİŞLETME BAĞLAMI:\n{$description}",
                    'role' => 'sales',
                    'openai_model' => 'gpt-5-mini',
                    'status' => 'active',
                    'ai_enabled' => true,
                    'subscription_status' => 'active',
                    'subscription_started_at' => now(),
                    'subscription_ends_at' => now()->addDay(),
                    'trial_message_limit' => 100,
                    'trial_messages_used' => 0,
                    'follow_up_enabled' => false,
                    'second_follow_up_enabled' => false,
                    'group_routing_enabled' => false,
                    'whatsapp_instance' => $instanceName,
                    'whatsapp_status' => 'connecting',
                ]);

                $whatsAppService->createInstance($instanceName);
                $whatsAppService->setWebhook($instanceName, url('/api/whatsapp/webhook'));

                Cache::put($cacheKey, $bot->id, now()->addDay());
            }

            $demoService->markConnectStarted($token, $bot);

            $state = $whatsAppService->connectionState((string) $bot->whatsapp_instance);

            if ($state === 'open') {
                $bot->forceFill(['whatsapp_status' => 'connected'])->save();
                $demoService->markConnected($token, $bot);

                return response()->json([
                    'success' => true,
                    'state' => 'open',
                ]);
            }

            $qr = $whatsAppService->getQrCode((string) $bot->whatsapp_instance);

            return response()->json([
                'success' => true,
                'state' => $state,
                'qr' => $qr,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'WhatsApp bağlantısı hazırlanamadı. Lütfen tekrar deneyin.',
            ], 500);
        }
    }

    public function whatsappStatus(
        string $token,
        WaiLeadDemoService $demoService,
        WhatsAppService $whatsAppService
    ): JsonResponse {
        if (! is_array($demoService->get($token))) {
            return response()->json(['success' => false], 404);
        }

        $botId = Cache::get('wai_lead_demo_bot:'.$token);
        $bot = $botId ? AiBot::query()->find($botId) : null;

        if (! $bot) {
            return response()->json([
                'success' => true,
                'state' => 'not_started',
            ]);
        }

        try {
            $state = $whatsAppService->connectionState((string) $bot->whatsapp_instance);

            if ($state === 'open') {
                if ($bot->whatsapp_status !== 'connected') {
                    $bot->forceFill(['whatsapp_status' => 'connected'])->save();
                }

                $demoService->markConnected($token, $bot);
            }

            return response()->json([
                'success' => true,
                'state' => $state,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'state' => 'error',
            ], 500);
        }
    }
}
