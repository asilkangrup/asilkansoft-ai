<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TextileConversationReset extends Command
{
    protected $signature = 'wai:textile-reset {phone : WhatsApp number, digits only or formatted}';

    protected $description = 'Reset one bot 51 textile conversation for a clean test session';

    public function handle(): int
    {
        $phone = preg_replace('/\D+/', '', (string) $this->argument('phone')) ?: '';

        if ($phone === '') {
            $this->error('Phone number is required.');
            return self::FAILURE;
        }

        if (str_starts_with($phone, '0')) {
            $phone = '90'.substr($phone, 1);
        } elseif (strlen($phone) === 10) {
            $phone = '90'.$phone;
        }

        $sessionId = 'whatsapp:51:'.$phone;
        $archiveSession = 'archived:'.$sessionId.':'.now()->format('YmdHis');

        ChatMessage::query()
            ->where('ai_bot_id', 51)
            ->where('session_id', $sessionId)
            ->update(['session_id' => $archiveSession]);

        ConversationControl::query()
            ->where('ai_bot_id', 51)
            ->where('whatsapp_number', $phone)
            ->update([
                'human_takeover' => false,
                'taken_over_at' => null,
                'released_at' => now(),
                'unread_count' => 0,
            ]);

        Cache::store('database')->forget('textile_demo_state:51:'.$phone);
        Cache::store('database')->forget('textile_text_burst:51:'.$phone);

        $activeMessages = ChatMessage::query()
            ->where('ai_bot_id', 51)
            ->where('session_id', $sessionId)
            ->count();

        $takeover = (bool) ConversationControl::query()
            ->where('ai_bot_id', 51)
            ->where('whatsapp_number', $phone)
            ->value('human_takeover');

        $stateExists = Cache::store('database')->has('textile_demo_state:51:'.$phone);

        $this->info('RESET_OK');
        $this->line('phone='.$phone);
        $this->line('active_messages='.$activeMessages);
        $this->line('human_takeover='.($takeover ? 'true' : 'false'));
        $this->line('state='.($stateExists ? 'present' : 'clear'));

        return self::SUCCESS;
    }
}
