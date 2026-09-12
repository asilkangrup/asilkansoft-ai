<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-natural] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-natural] service file unreadable; skipping.\n");
    exit(0);
}

$original = $source;

$source = str_replace(
    'return "Merhaba 👋\\n\\n*Baskılı tekstil siparişinizi birlikte hazırlayalım.*\\n\\nÜrün modelini, rengi ve adedi tek mesajda yazabilirsiniz.\\nÖrnek: *250 adet siyah oversize tişört.*";',
    'return "Merhaba 👋\\n\\nNasıl yardımcı olabilirim?";',
    $source,
);

$source = str_replace(
    'return "Tişört baskısı için ürün modelini, rengi ve adedi tek mesajda yazabilirsiniz.\\n\\nÖrnek: *250 adet siyah oversize tişört.*";',
    'return "Nasıl yardımcı olabilirim?";',
    $source,
);

$source = str_replace(
    'Kaynak bilgisi henüz verilmediyse bunu sorman mümkündür; daha önce verilmişse tekrar sorma.',
    'Tişörtleri bizden mi alacaksınız veya ürünleri siz mi getireceksiniz diye kendiliğinden sorma. Müşteri kendi ürününü getireceğini açıkça söylerse bunu kaydet ve ilgili kuralları uygula; aksi halde kaynak bilgisini isteme.',
    $source,
);

if ($source === $original) {
    echo "[textile-natural] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-natural] syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-natural] syntax OK.\n";
