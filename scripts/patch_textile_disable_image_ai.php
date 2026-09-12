<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileAttachmentService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-disable-image-ai] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-disable-image-ai] service file unreadable; skipping.\n");
    exit(0);
}

if (str_contains($source, 'TEXTILE_IMAGE_AI_DISABLED_V1')) {
    echo "[textile-disable-image-ai] already applied.\n";
    exit(0);
}

$needle = <<<'PHP'
        $isImage = str_starts_with($mime, 'image/');
PHP;

$replacement = <<<'PHP'
        $isImage = str_starts_with($mime, 'image/');

        // TEXTILE_IMAGE_AI_DISABLED_V1
        // Image uploads are used directly as printable artwork. No OpenAI vision call.
        if ($isImage) {
            return [
                'kind' => 'artwork',
                'summary' => '',
                'order_text' => '',
                'order_items' => [],
                'artwork_bbox' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100],
                'contains_printable_artwork' => true,
                'reference_only' => false,
                'needs_human' => false,
                'artwork_base64' => $base64,
            ];
        }
PHP;

if (! str_contains($source, $needle)) {
    fwrite(STDERR, "[textile-disable-image-ai] anchor missing.\n");
    exit(1);
}

$source = str_replace($needle, $replacement, $source, $count);
if ($count !== 1) {
    fwrite(STDERR, "[textile-disable-image-ai] replacement count invalid.\n");
    exit(1);
}

file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "[textile-disable-image-ai] syntax check failed.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-disable-image-ai] image AI disabled; syntax OK.\n";
