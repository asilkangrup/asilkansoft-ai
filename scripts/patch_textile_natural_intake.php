<?php

$referenceImagePatch = __DIR__.'/patch_textile_reference_image_understanding.php';
if (is_file($referenceImagePatch)) {
    require $referenceImagePatch;
}

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-natural-intake] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-natural-intake] service file unreadable; skipping.\n");
    exit(0);
}

$original = $source;

$oldWelcome = <<<'PHP'
    private function welcomeMessage(): string
    {
        return "Merhaba 👋\n\n*Baskılı tekstil siparişinizi birlikte hazırlayalım.*\n\nÜrün modelini, rengi ve adedi tek mesajda yazabilirsiniz.\nÖrnek: *250 adet siyah oversize tişört.*";
    }
PHP;

$newWelcome = <<<'PHP'
    private function welcomeMessage(): string
    {
        return "Merhaba 👋\n\nNasıl yardımcı olabilirim? Baskı yaptırmak istediğiniz ürünü, adedi veya aklınızdaki talebi yazabilirsiniz. Tek adet ve perakende taleplerde de yardımcı olabiliriz.";
    }
PHP;

if (str_contains($source, $oldWelcome)) {
    $source = str_replace($oldWelcome, $newWelcome, $source, $welcomeCount);
    echo "[textile-natural-intake] natural welcome updated.\n";
}

$phraseAnchor = <<<'PHP'
            'tişörtleri ben getirsem', 'tisortleri ben getirsem', 'ben getirsem',
PHP;

$phraseReplacement = <<<'PHP'
            'tişörtleri ben getirsem', 'tisortleri ben getirsem', 'ben getirsem',
            'ürünleri ben getireceğim', 'urunleri ben getirecegim',
            'ürünleri kendim getireceğim', 'urunleri kendim getirecegim',
            'kendi ürünüm', 'kendi urunum', 'kendi ürünlerim', 'kendi urunlerim',
            'ürün benden', 'urun benden', 'ürünler benden', 'urunler benden',
            'ürünleri müşteri getiriyor', 'urunleri musteri getiriyor',
            'kendimiz getireceğiz', 'kendimiz getirecegiz',
            'kendi tişörtüm', 'kendi tisortum', 'kendi tişörtlerim', 'kendi tisortlerim',
PHP;

if (! str_contains($source, "'ürünleri ben getireceğim'")) {
    if (str_contains($source, $phraseAnchor)) {
        $source = str_replace($phraseAnchor, $phraseReplacement, $source, $phraseCount);
        echo "[textile-natural-intake] supplied-product phrases expanded.\n";
    } else {
        fwrite(STDERR, "[textile-natural-intake] supplied-product anchor missing.\n");
    }
}

$instructionAnchor = <<<'PHP'
Müşterinin son mesajındaki asıl soruya önce doğrudan ve doğal biçimde cevap ver.
PHP;

$instructionReplacement = <<<'PHP'
Müşterinin son mesajındaki asıl soruya önce doğrudan ve doğal biçimde cevap ver.
Müşteri bir bilgiyi daha önce söylediyse aynı bilgiyi tekrar sorma. Özellikle müşteri kendi ürününü/tişörtünü getireceğini söylediyse "tişörtleri bizden mi alacaksınız?" benzeri soruyu kesinlikle tekrar sorma; mevcut sipariş bağlamındaki "Tişört kaynağı" bilgisini kullan.
Müşteriyi yüksek adetli siparişe zorlayan bir dil kullanma; tek adet ve perakende talepleri de doğal şekilde kabul et.
PHP;

if (! str_contains($source, 'Müşteri bir bilgiyi daha önce söylediyse aynı bilgiyi tekrar sorma.')) {
    if (str_contains($source, $instructionAnchor)) {
        $source = str_replace($instructionAnchor, $instructionReplacement, $source, $instructionCount);
        echo "[textile-natural-intake] no-repeat rule added.\n";
    } else {
        fwrite(STDERR, "[textile-natural-intake] live instruction anchor missing.\n");
    }
}

if ($source === $original) {
    echo "[textile-natural-intake] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-natural-intake] syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-natural-intake] syntax OK.\n";
