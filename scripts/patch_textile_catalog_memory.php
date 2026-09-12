<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-catalog-memory] service file missing.\n");
    exit(1);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-catalog-memory] service unreadable.\n");
    exit(1);
}

$original = $source;

// Persist fabric/material preference in order state so it survives beyond the
// short LLM chat-history window.
if (! str_contains($source, "'fabric_preference' => null")) {
    $needle = "            'customer_supplied' => null,\n            'discount_requested' => false,\n";
    $replacement = "            'customer_supplied' => null,\n            'fabric_preference' => null,\n            'discount_requested' => false,\n";
    if (! str_contains($source, $needle)) {
        fwrite(STDERR, "[textile-catalog-memory] state anchor missing.\n");
        exit(1);
    }
    $source = str_replace($needle, $replacement, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-catalog-memory] state replacement invalid.\n");
        exit(1);
    }
}

if (! str_contains($source, 'TEXTILE_FABRIC_MEMORY_V1')) {
    $needle = "        \$lower = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], \$message));\n";
    $replacement = $needle.<<<'PHP'

        // TEXTILE_FABRIC_MEMORY_V1
        // Critical order facts must live in state, not only in the last few
        // chat messages. This prevents the assistant asking the same fabric
        // question again later in a long conversation.
        if (
            str_contains($lower, '%100 pamuk')
            || str_contains($lower, '100% pamuk')
            || str_contains($lower, 'yüzde 100 pamuk')
            || str_contains($lower, 'yuzde 100 pamuk')
            || str_contains($lower, 'tam pamuk')
        ) {
            $state['fabric_preference'] = '%100 pamuk';
        } elseif (
            str_contains($lower, 'polyester')
            && ! str_contains($lower, 'şapka')
            && ! str_contains($lower, 'sapka')
        ) {
            $state['fabric_preference'] = 'polyester';
        }
PHP;

    if (! str_contains($source, $needle)) {
        fwrite(STDERR, "[textile-catalog-memory] parseText anchor missing.\n");
        exit(1);
    }
    $source = str_replace($needle, $replacement, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-catalog-memory] parseText replacement invalid.\n");
        exit(1);
    }
}

if (! str_contains($source, "            'fabric_preference',\n")) {
    $needle = "            'customer_supplied',\n            'discount_requested',\n";
    $replacement = "            'customer_supplied',\n            'fabric_preference',\n            'discount_requested',\n";
    if (! str_contains($source, $needle)) {
        fwrite(STDERR, "[textile-catalog-memory] stateProgressed anchor missing.\n");
        exit(1);
    }
    $source = str_replace($needle, $replacement, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-catalog-memory] stateProgressed replacement invalid.\n");
        exit(1);
    }
}

if (! str_contains($source, "'Kumaş tercihi' =>")) {
    $needle = "            'Beden' => \$state['sizes'] ?? 'henüz belirtilmedi',\n";
    $replacement = $needle."            'Kumaş tercihi' => \$state['fabric_preference'] ?? 'henüz belirtilmedi',\n";
    if (! str_contains($source, $needle)) {
        fwrite(STDERR, "[textile-catalog-memory] live status anchor missing.\n");
        exit(1);
    }
    $source = str_replace($needle, $replacement, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-catalog-memory] live status replacement invalid.\n");
        exit(1);
    }
}

$oldCatalogue = 'Güncel ürün kataloğu: kapüşonlu sweatshirt, regular/bisiklet yaka tişört, siyah ve beyaz oversize tişört, polo yaka tişört, pamuklu şapka ve polyester şapka.';
$newCatalogue = 'Güncel ürün kataloğu: kapüşonlu sweatshirt, fermuarlı sweatshirt, basic/regular tişört, oversize tişört, polo yaka tişört, aşçı önlüğü, inşaat yeleği, şapka, bez çanta ve çocuk tişört. Oversize bir renk değil, tişört kalıbı/modelidir. Renk bilgisini modelden ayrı değerlendir. Siyah ve beyaz standart seçeneklerdir; diğer renklerde üretim minimum 60 adettir.';
if (str_contains($source, $oldCatalogue)) {
    $source = str_replace($oldCatalogue, $newCatalogue, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-catalog-memory] catalogue replacement invalid.\n");
        exit(1);
    }
}

if (! str_contains($source, 'Kumaş tercihi state içinde biliniyorsa')) {
    $needle = "Müşteri bir bilgiyi daha önce söylediyse aynı bilgiyi tekrar sorma.";
    $replacement = "Müşteri bir bilgiyi daha önce söylediyse aynı bilgiyi tekrar sorma. Kumaş tercihi state içinde biliniyorsa (ör. %100 pamuk) aradan kaç mesaj geçerse geçsin tekrar kumaşı sorma; kayıtlı bilgiyi kullan. Oversize bir renk değil kalıp/modeldir; renk bilgisini her zaman ayrı ele al.";
    if (str_contains($source, $needle)) {
        $source = str_replace($needle, $replacement, $source, $count);
        if ($count < 1) {
            fwrite(STDERR, "[textile-catalog-memory] instruction replacement failed.\n");
            exit(1);
        }
    }
}

if ($source === $original) {
    echo "[textile-catalog-memory] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$cmd = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($cmd, $output, $exitCode);
if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-catalog-memory] syntax failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-catalog-memory] syntax OK; catalogue and persistent facts enabled.\n";
