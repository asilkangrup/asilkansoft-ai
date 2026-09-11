<?php

/**
 * İstanbul Tişört Baskı (#51) için manuel mesaj sonrası AI takeover kuralı.
 *
 * Botun kendi API çıkışları isApiOutbound() ile ayırt edilir. Gerçek bir
 * personel/bağlı cihaz mesajı fromMe=true olarak geldiğinde o müşterinin
 * ConversationControl kaydı human_takeover=true yapılır ve AI susar.
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

$old = <<<'PHP'
        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            // The textile demo may be tested from both linked devices. Outgoing
            // messages must never pause the automated order flow.
            return true;
        }
PHP;

$new = <<<'PHP'
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

if (str_contains($source, $new)) {
    echo "[textile-manual-takeover] already applied.\n";
    exit(0);
}

if (! str_contains($source, $old)) {
    fwrite(STDERR, "[textile-manual-takeover] anchor missing; no changes made.\n");
    exit(1);
}

$updated = str_replace($old, $new, $source, $count);
if ($count !== 1) {
    fwrite(STDERR, "[textile-manual-takeover] unexpected replacement count: {$count}.\n");
    exit(1);
}

file_put_contents($path, $updated);

$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $source);
    fwrite(STDERR, "[textile-manual-takeover] syntax failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-manual-takeover] applied; syntax OK.\n";
