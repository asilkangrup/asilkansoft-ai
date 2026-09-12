<?php

$attachmentPath = dirname(__DIR__).'/app/Services/Textile/TextileAttachmentService.php';
$inboundPath = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

foreach ([$attachmentPath, $inboundPath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "[textile-reference-image] missing file: {$path}\n");
        exit(1);
    }
}

$attachment = file_get_contents($attachmentPath);
$inbound = file_get_contents($inboundPath);
if (! is_string($attachment) || ! is_string($inbound)) {
    fwrite(STDERR, "[textile-reference-image] could not read source files.\n");
    exit(1);
}

$attachmentOriginal = $attachment;
$inboundOriginal = $inbound;

// 1) Teach vision analysis to distinguish a customer reference/marked-up product
// photo from actual printable artwork.
if (! str_contains($attachment, 'TEXTILE_REFERENCE_MARKUP_V1')) {
    $needle = <<<'TXT'
6. Hazır tasarım/mockup üzerinde ön ve arka görünüm birlikte varsa bunu iki ayrı baskı konumu olarak belirt. Küçük göğüs baskısını "ön sol göğüs" veya "ön sağ göğüs", büyük ön baskıyı "ön büyük", büyük arka baskıyı "sırtta büyük" şeklinde yaz. Emin olmadığın sağ/sol tarafı uydurma; yalnız "ön göğüs" diye belirt.
7. İnsan yüzünü veya kişisel fotoğrafı logo olarak yeniden üretme. Sadece müşterinin istediği mevcut baskıyı tespit et.
8. Emin olmadığın bilgiyi uydurma; null veya boş bırak.
TXT;

    $replacement = <<<'TXT'
6. Hazır tasarım/mockup üzerinde ön ve arka görünüm birlikte varsa bunu iki ayrı baskı konumu olarak belirt. Küçük göğüs baskısını "ön sol göğüs" veya "ön sağ göğüs", büyük ön baskıyı "ön büyük", büyük arka baskıyı "sırtta büyük" şeklinde yaz. Emin olmadığın sağ/sol tarafı uydurma; yalnız "ön göğüs" diye belirt.
7. TEXTILE_REFERENCE_MARKUP_V1: Fotoğrafta müşteri tarafından sonradan çizilmiş daire, ok, fosforlu işaret, karalama veya benzeri bir işaret varsa bu işaretin gösterdiği bölgeyi baskı konumu olarak yorumla. Bu çizimi/logo olmayan işareti baskı tasarımı sayma. Böyle bir görselde gerçek bir baskı tasarımı görünmüyorsa contains_printable_artwork=false ve reference_only=true döndür. order_text içine görülen ürün, renk ve işaretlenen baskı konumunu açık Türkçe ile yaz. Örnek: "Beyaz tişört, ön sol göğüs bölgesi baskı alanı olarak işaretlenmiş."
8. Bir fiziksel ürün fotoğrafı yalnızca model, renk, kalıp veya baskı konumunu göstermek amacıyla gönderilmişse reference_only=true döndür. Referans fotoğrafını müşterinin yüklediği logo gibi kullanma.
9. Ürünün üzerinde gerçekten basılmış/uygulanmış ve müşterinin baskıda kullanabileceği ayrı bir tasarım, logo veya yazı varsa contains_printable_artwork=true olabilir. Daire, ok ve elle çizilmiş yönlendirme işaretleri printable artwork değildir.
10. İnsan yüzünü veya kişisel fotoğrafı logo olarak yeniden üretme. Sadece müşterinin istediği mevcut baskıyı tespit et.
11. Emin olmadığın bilgiyi uydurma; null veya boş bırak.
TXT;

    if (! str_contains($attachment, $needle)) {
        fwrite(STDERR, "[textile-reference-image] analysis prompt anchor missing.\n");
        exit(1);
    }
    $attachment = str_replace($needle, $replacement, $attachment, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-reference-image] prompt replacement count invalid.\n");
        exit(1);
    }

    $schemaNeedle = <<<'TXT'
  "contains_printable_artwork":true,
  "needs_human":false
TXT;
    $schemaReplacement = <<<'TXT'
  "contains_printable_artwork":true,
  "reference_only":false,
  "needs_human":false
TXT;
    $attachment = str_replace($schemaNeedle, $schemaReplacement, $attachment, $schemaCount);
    if ($schemaCount !== 1) {
        fwrite(STDERR, "[textile-reference-image] schema anchor missing.\n");
        exit(1);
    }
}

// Normalize reference_only in returned analysis.
if (! str_contains($attachment, "$result['reference_only'] = (bool)")) {
    $needle = <<<'PHP'
        $result['order_items'] = is_array($result['order_items'] ?? null)
            ? array_slice($result['order_items'], 0, 20)
            : [];
PHP;
    $replacement = $needle . <<<'PHP'
        $result['reference_only'] = (bool) ($result['reference_only'] ?? false);
PHP;
    if (! str_contains($attachment, $needle)) {
        fwrite(STDERR, "[textile-reference-image] normalize anchor missing.\n");
        exit(1);
    }
    $attachment = str_replace($needle, $replacement, $attachment, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-reference-image] normalize replacement invalid.\n");
        exit(1);
    }
}

// Do not crop every printed_product as if it were artwork. Crop only when
// the vision model explicitly found printable artwork.
$oldExtract = <<<'PHP'
            if (
                $result['kind'] === 'printed_product'
                || (($result['contains_printable_artwork'] ?? false) && is_array($result['artwork_bbox'] ?? null))
            ) {
PHP;
$newExtract = <<<'PHP'
            if (
                ($result['contains_printable_artwork'] ?? false)
                && ! ($result['reference_only'] ?? false)
                && is_array($result['artwork_bbox'] ?? null)
            ) {
PHP;
if (str_contains($attachment, $oldExtract)) {
    $attachment = str_replace($oldExtract, $newExtract, $attachment, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-reference-image] extract replacement invalid.\n");
        exit(1);
    }
}

// 2) In inbound flow, reference/marked-up product images must advance state
// (product/color/position) but must never be stored as logo artwork.
if (! str_contains($inbound, 'TEXTILE_REFERENCE_IMAGE_FLOW_V1')) {
    $needle = <<<'PHP'
                $hasPrintableArtwork = (bool) ($analysis['contains_printable_artwork'] ?? false);
                if (($analysis['kind'] ?? null) === 'order_document' && ! $hasPrintableArtwork) {
PHP;
    $replacement = <<<'PHP'
                $hasPrintableArtwork = (bool) ($analysis['contains_printable_artwork'] ?? false);
                $referenceOnly = (bool) ($analysis['reference_only'] ?? false);

                // TEXTILE_REFERENCE_IMAGE_FLOW_V1
                // A customer may send a product photo only to show a garment,
                // colour or desired print area (often circled/arrowed). Treat it
                // as order context, not as a logo file.
                if (
                    ($analysis['kind'] ?? null) === 'printed_product'
                    && ($referenceOnly || ! $hasPrintableArtwork)
                ) {
                    $summary = trim((string) ($analysis['summary'] ?? ''));
                    $attachmentReply = "Görseli aldım ✓";
                    if ($summary !== '') {
                        $attachmentReply .= "\n\n".$summary;
                    }
                    $attachmentReply .= "\n\n".$this->nextQuestion($state);
                } elseif (($analysis['kind'] ?? null) === 'order_document' && ! $hasPrintableArtwork) {
PHP;
    if (! str_contains($inbound, $needle)) {
        fwrite(STDERR, "[textile-reference-image] inbound anchor missing.\n");
        exit(1);
    }
    $inbound = str_replace($needle, $replacement, $inbound, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-reference-image] inbound replacement invalid.\n");
        exit(1);
    }
}

if ($attachment === $attachmentOriginal && $inbound === $inboundOriginal) {
    echo "[textile-reference-image] no changes required.\n";
    exit(0);
}

file_put_contents($attachmentPath, $attachment);
file_put_contents($inboundPath, $inbound);

foreach ([$attachmentPath, $inboundPath] as $path) {
    $cmd = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        file_put_contents($attachmentPath, $attachmentOriginal);
        file_put_contents($inboundPath, $inboundOriginal);
        fwrite(STDERR, "[textile-reference-image] syntax failed; originals restored.\n".implode("\n", $output)."\n");
        exit(1);
    }
}

echo "[textile-reference-image] syntax OK; reference image understanding enabled.\n";
