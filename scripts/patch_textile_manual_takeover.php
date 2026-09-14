<?php

/**
 * İstanbul Tişört Baskı (#51) için manuel mesaj sonrası AI takeover kuralı
 * ve yalnızca siyah/beyaz otomatik tişört mockup kuralı.
 *
 * Botun kendi API çıkışları isApiOutbound() ile ayırt edilir. Gerçek bir
 * personel/bağlı cihaz mesajı fromMe=true olarak geldiğinde o müşterinin
 * ConversationControl kaydı human_takeover=true yapılır ve AI susar.
 *
 * Regular / polo / oversize tişörtlerde İstanbul botu yalnızca siyah ve
 * beyaz otomatik önizleme üretir. Diğer renklerde müşteri Fatih Bey'e
 * devredilir ve AI o konuşmada susar.
 */

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-manual-takeover] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-manual-takeover] service file could not be read; skipping.\n");
    exit(0);
}

$original = $source;
$changed = false;

$oldTakeover = <<<'PHP'
        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            // The textile demo may be tested from both linked devices. Outgoing
            // messages must never pause the automated order flow.
            return true;
        }
PHP;

$newTakeover = <<<'PHP'
        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            // İstanbul Tişört Baskı pilotunda personel müşteriye manuel olarak
            // yazdığı anda AI o konuşmada susar. Botun kendi API ile gönderdiği
            // mesajlar isApiOutbound() ile ayırt edilir ve takeover tetiklemez.
            if ((int) $bot->id === 51 && ! $this->isApiOutbound($instance, $phone, $payload)) {
                $this->pauseAfterManualReply($bot, $phone);
            }

            return true;
        }
PHP;

if (str_contains($source, $newTakeover)) {
    echo "[textile-manual-takeover] takeover already applied.\n";
} elseif (str_contains($source, $oldTakeover)) {
    $source = str_replace($oldTakeover, $newTakeover, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-manual-takeover] unexpected takeover replacement count: {$count}.\n");
        exit(1);
    }
    $changed = true;
    echo "[textile-manual-takeover] takeover applied.\n";
} else {
    fwrite(STDERR, "[textile-manual-takeover] takeover anchor missing.\n");
}

$blackWhiteMarker = 'Otomatik önizlemede şu an sadece *siyah* ve *beyaz* tişört üzerinden çalışabiliyoruz.';

$oldColorRule = <<<'PHP'
        if (
            $isTextMessage
            && ! ($state['bekir_catalogue'] ?? false)
            && $stateChanged
            && ($state['color'] ?? null)
            && ! in_array($state['color'], ['black', 'white'], true)
            && ($state['quantity'] ?? null)
            && (int) $state['quantity'] < 60
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                "Siyah ve beyaz dışındaki renklerde üretim minimum *60 adettir*.\n\nAdedi 60 veya üzerine çıkarabiliriz; isterseniz mevcut adette siyah ya da beyaz oversize seçeneğiyle devam edebiliriz.",
            );
            $this->consumeTrial($bot);

            return true;
        }
PHP;

$newColorRule = <<<'PHP'
        if (
            $isTextMessage
            && (int) $bot->id === 51
            && ! ($state['bekir_catalogue'] ?? false)
            && $stateChanged
            && ($state['product_category'] ?? null) === 'shirt'
            && ($state['color'] ?? null)
            && ! in_array($state['color'], ['black', 'white'], true)
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                "Otomatik önizlemede şu an sadece *siyah* ve *beyaz* tişört üzerinden çalışabiliyoruz.\n\n*Regular, polo ve oversize* için farklı renk taleplerinizi *Fatih Bey* sizin için manuel olarak hazırlayacaktır.",
            );
            $this->pauseAfterManualReply($bot, $phone);
            $this->consumeTrial($bot);

            return true;
        }

        if (
            $isTextMessage
            && (int) $bot->id !== 51
            && ! ($state['bekir_catalogue'] ?? false)
            && $stateChanged
            && ($state['color'] ?? null)
            && ! in_array($state['color'], ['black', 'white'], true)
            && ($state['quantity'] ?? null)
            && (int) $state['quantity'] < 60
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                "Siyah ve beyaz dışındaki renklerde üretim minimum *60 adettir*.\n\nAdedi 60 veya üzerine çıkarabiliriz; isterseniz mevcut adette siyah ya da beyaz oversize seçeneğiyle devam edebiliriz.",
            );
            $this->consumeTrial($bot);

            return true;
        }
PHP;

if (str_contains($source, $blackWhiteMarker)) {
    echo "[textile-manual-takeover] black-white rule already applied.\n";
} elseif (str_contains($source, $oldColorRule)) {
    $source = str_replace($oldColorRule, $newColorRule, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-manual-takeover] unexpected color-rule replacement count: {$count}.\n");
        exit(1);
    }
    $changed = true;
    echo "[textile-manual-takeover] black-white rule applied.\n";
} else {
    fwrite(STDERR, "[textile-manual-takeover] color-rule anchor missing.\n");
}

if (! $changed) {
    echo "[textile-manual-takeover] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);

$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-manual-takeover] syntax failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-manual-takeover] applied; syntax OK.\n";
