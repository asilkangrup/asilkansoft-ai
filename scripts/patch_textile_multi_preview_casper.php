<?php

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-multi-preview-casper] service missing; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-multi-preview-casper] service unreadable; skipping.\n");
    exit(0);
}

if (str_contains($source, 'PILOT_MULTI_PREVIEW_CASPER_V1')) {
    echo "[textile-multi-preview-casper] already applied.\n";
    exit(0);
}

$old = <<<'PHP'
                $state['mockup_sent'] = true;
                $state = $this->updateSalesStage($state);
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
PHP;

$new = <<<'PHP'
                $state['mockup_sent'] = true;

                // PILOT_MULTI_PREVIEW_CASPER_V1
                if ((int) $bot->id === 51 && ! ($state['casper_group_notified'] ?? false)) {
                    $state['casper_group_notified'] = $this->notifyCasperGroupAfterMockup(
                        $bot,
                        $conversation,
                        $instance,
                        $phone,
                        $state,
                    );
                }

                $state = $this->updateSalesStage($state);
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
PHP;

if (! str_contains($source, $old)) {
    fwrite(STDERR, "[textile-multi-preview-casper] anchor missing.\n");
    exit(1);
}

$source = str_replace($old, $new, $source, $count);
if ($count !== 1) {
    fwrite(STDERR, "[textile-multi-preview-casper] replacement count invalid: {$count}.\n");
    exit(1);
}

$original = file_get_contents($path);
file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-multi-preview-casper] syntax failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-multi-preview-casper] syntax OK.\n";
