<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Services\MemoryService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class TextileDemoPaymentController extends Controller
{
    public function show(Request $request): View
    {
        $orderId = trim((string) $request->query('order', ''));
        $order = Cache::store('database')->get('textile_demo_order:'.$orderId);

        abort_unless(is_array($order) && $orderId !== '', 404);

        return view('textile-demo-payment', [
            'order' => $order,
            'orderId' => $orderId,
            'quote' => $this->quote($order),
            'paid' => (bool) Cache::store('database')->get('textile_demo_payment:'.$orderId, false),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $orderId = trim((string) $request->query('order', ''));
        $order = Cache::store('database')->get('textile_demo_order:'.$orderId);

        abort_unless(is_array($order) && $orderId !== '', 404);

        if (! Cache::store('database')->add('textile_demo_payment:'.$orderId, true, now()->addHours(2))) {
            return back()->with('success', 'Demo ödeme daha önce tamamlandı.');
        }

        try {
            $bot = AiBot::query()->find((int) ($order['bot_id'] ?? 0));
            $conversation = ConversationControl::query()->find((int) ($order['conversation_id'] ?? 0));
            $instance = trim((string) ($order['instance'] ?? ''));
            $phone = trim((string) ($order['phone'] ?? ''));

            if ($bot && $conversation && $instance !== '' && $phone !== '') {
                $answer = "✅ Demo ödeme başarıyla tamamlandı.\n\n"
                    .'Sipariş No: *#'.$orderId."*\n"
                    ."Siparişiniz üretim planına aktarıldı. Gerçek sistemde bu anda ödeme kaydı, üretim görevi ve yönetici bildirimi otomatik oluşur.";

                Cache::store('database')->put(
                    'wai_api_outbound:'.sha1($instance.'|'.$phone.'|'.$answer),
                    true,
                    now()->addMinutes(5),
                );
                $send = app(WhatsAppService::class)->sendText($instance, $phone, $answer);

                app(MemoryService::class)->mesajKaydet(
                    userId: $bot->user_id,
                    aiBotId: $bot->id,
                    sessionId: $conversation->session_id,
                    role: 'assistant',
                    message: $answer,
                    mediaContext: [
                        'message_id' => data_get($send, 'key.id') ?? data_get($send, 'messageId') ?? data_get($send, 'id'),
                    ],
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Demo ödeme tamamlandı ve sipariş üretime aktarıldı.');
    }

    private function quote(array $state): array
    {
        $product = (string) ($state['product'] ?? 'Premium Oversize Tişört');
        $base = match ($product) {
            'Regular Fit Tişört' => 135,
            'Polo Yaka Tişört' => 185,
            'Heavy Cotton Tişört' => 205,
            default => 155,
        };
        $print = ($state['print_type'] ?? 'DTF Baskı') === 'Tek Renk Serigrafi' ? 26 : 34;
        $quantity = max(10, (int) ($state['quantity'] ?? 250));
        $discount = $quantity >= 1000 ? .14 : ($quantity >= 500 ? .10 : ($quantity >= 250 ? .06 : ($quantity >= 100 ? .03 : 0)));
        $unit = (int) round(($base + $print) * (1 - $discount));

        return ['unit' => $unit, 'total' => $unit * $quantity, 'quantity' => $quantity];
    }
}
