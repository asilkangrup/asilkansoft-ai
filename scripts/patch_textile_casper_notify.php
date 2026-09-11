<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-casper] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-casper] service file unreadable; skipping.\n");
    exit(0);
}

$original = $source;

if (! str_contains($source, 'private function notifyCasperGroupAfterMockup(')) {
    $anchor = "    private function conversation(AiBot \$bot, string \$sessionId, string \$phone, array \$payload): ConversationControl\n";
    $helper = <<<'PHP'
    private function notifyCasperGroupAfterMockup(
        AiBot $bot,
        ConversationControl $conversation,
        string $instance,
        string $phone,
        array $state
    ): bool {
        if ((int) $bot->id !== 51) {
            return false;
        }

        try {
            $positions = [(string) ($state['position'] ?? '')];
            foreach ((array) ($state['additional_prints'] ?? []) as $print) {
                $position = trim((string) ($print['position'] ?? ''));
                if ($position !== '') {
                    $positions[] = $position;
                }
            }
            $positions = array_values(array_unique(array_filter($positions)));
            $positionLabels = array_map(fn (string $position): string => $this->positionLabel($position), $positions);

            $colorMap = [
                'black' => 'Siyah',
                'white' => 'Beyaz',
                'red' => 'Kırmızı',
                'blue' => 'Mavi',
                'navy' => 'Lacivert',
                'green' => 'Yeşil',
                'gray' => 'Gri',
                'grey' => 'Gri',
            ];
            $color = (string) ($state['color'] ?? '-');
            $colorLabel = $colorMap[$color] ?? ucfirst($color);
            $name = trim((string) ($conversation->customer_name ?? ''));
            $name = $name !== '' ? $name : 'İsimsiz müşteri';
            $displayPhone = str_starts_with($phone, '+') ? $phone : '+'.$phone;

            $lines = [
                '🖨️ *Yeni baskı önizlemesi gönderildi*',
                '',
                'Müşteri: *'.$name.'*',
                'Telefon: *'.$displayPhone.'*',
                'Ürün: *'.((string) ($state['product'] ?? '-')).'*',
                'Adet: *'.((string) ($state['quantity'] ?? '-')).'*',
                'Renk: *'.$colorLabel.'*',
                'Baskı: *'.($positionLabels !== [] ? implode(', ', $positionLabels) : '-').'*',
                '',
                'Durum: Müşteri baskı görselini gönderdi ve WhatsApp üzerinden baskı önizlemesi iletildi.',
            ];

            $this->whatsAppService->sendGroupText(
                $instance,
                '120363414072361301@g.us',
                implode("\n", $lines),
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('TEXTILE CASPER GROUP NOTIFY FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'phone' => $phone,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

PHP;

    if (str_contains($source, $anchor)) {
        $source = str_replace($anchor, $helper.$anchor, $source, $count);
        echo "[textile-casper] helper added.\n";
    } else {
        fwrite(STDERR, "[textile-casper] helper anchor missing.\n");
    }
}

if (! str_contains($source, "casper_group_notified")) {
    $needle = "                \$state['mockup_sent'] = true;\n";
    $replacement = <<<'PHP'
                if (! ($state['casper_group_notified'] ?? false)) {
                    $state['casper_group_notified'] = $this->notifyCasperGroupAfterMockup(
                        $bot,
                        $conversation,
                        $instance,
                        $phone,
                        $state,
                    );
                }
                $state['mockup_sent'] = true;
PHP;

    if (str_contains($source, $needle)) {
        $source = preg_replace('/'.preg_quote($needle, '/').'/', str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement."\n"), $source, 1) ?? $source;
        echo "[textile-casper] mockup notification hook added.\n";
    } else {
        fwrite(STDERR, "[textile-casper] mockup hook anchor missing.\n");
    }
}

if ($source === $original) {
    echo "[textile-casper] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-casper] syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-casper] syntax OK.\n";
