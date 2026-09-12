<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileAttachmentService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-ai-cost-guard] attachment service missing; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-ai-cost-guard] attachment service unreadable; skipping.\n");
    exit(0);
}

$original = $source;
$source = str_replace("'detail' => 'high'", "'detail' => 'low'", $source);
$source = str_replace("$model = trim((string) ($bot?->openai_model ?: 'gpt-5-mini'));", "$model = 'gpt-5-mini';", $source);
$source = str_replace("'max_output_tokens' => 1000", "'max_output_tokens' => 400", $source);

if ($source === $original) {
    echo "[textile-ai-cost-guard] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$cmd = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($cmd, $output, $exitCode);
if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-ai-cost-guard] syntax failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-ai-cost-guard] enabled.\n";
