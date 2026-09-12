<?php

/**
 * Allow textile conversations to use WhatsApp LID identities when no phone-number
 * JID is available. This keeps username-only/new WhatsApp contacts replyable and
 * preserves human-takeover behavior for the İstanbul Tişört pilot as well.
 */

$servicePath = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';
$providerPath = dirname(__DIR__).'/app/Providers/AppServiceProvider.php';

foreach ([$servicePath, $providerPath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "[textile-lid] missing file: {$path}\n");
        exit(1);
    }
}

$service = file_get_contents($servicePath);
$provider = file_get_contents($providerPath);
$serviceOriginal = $service;
$providerOriginal = $provider;

$oldService = <<<'PHP'
        // Bekir: username-only WhatsApp contacts may have no phone-number JID.
        $jid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if (($payload['instance'] ?? '') === 'bekir-tekstil-53' && preg_match('/^[0-9]+@lid$/', $jid)) {
            return $jid;
        }
PHP;

$newService = <<<'PHP'
        // Username-only WhatsApp contacts may have no phone-number JID.
        // Keep the stable LID as the conversation identity so every textile bot
        // can answer brand-new chats even when WhatsApp hides the phone number.
        $jid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if (preg_match('/^[0-9]+@lid$/', $jid)) {
            return $jid;
        }
PHP;

if (str_contains($service, $oldService)) {
    $service = str_replace($oldService, $newService, $service, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-lid] service replacement count invalid: {$count}\n");
        exit(1);
    }
} elseif (! str_contains($service, 'Keep the stable LID as the conversation identity')) {
    fwrite(STDERR, "[textile-lid] service anchor missing\n");
    exit(1);
}

$oldProvider = <<<'PHP'
            $instance = trim((string) data_get($request->all(), 'instance', ''));
            $remoteJid = trim((string) data_get($request->all(), 'data.key.remoteJid', ''));
            $phone = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';

            if ($instance !== '' && $phone !== '' && ! str_ends_with($remoteJid, '@g.us')) {
PHP;

$newProvider = <<<'PHP'
            $instance = trim((string) data_get($request->all(), 'instance', ''));
            $remoteJid = trim((string) data_get($request->all(), 'data.key.remoteJid', ''));
            $remoteJidAlt = trim((string) data_get($request->all(), 'data.key.remoteJidAlt', ''));

            if ($remoteJidAlt !== '' && ! str_ends_with($remoteJidAlt, '@lid')) {
                $phone = preg_replace('/\D+/', '', explode('@', $remoteJidAlt)[0] ?? '') ?? '';
            } elseif (preg_match('/^[0-9]+@lid$/', $remoteJid)) {
                $phone = $remoteJid;
            } else {
                $phone = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';
            }

            if ($instance !== '' && $phone !== '' && ! str_ends_with($remoteJid, '@g.us')) {
PHP;

if (str_contains($provider, $oldProvider)) {
    $provider = str_replace($oldProvider, $newProvider, $provider, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-lid] provider replacement count invalid: {$count}\n");
        exit(1);
    }
} elseif (! str_contains($provider, '$remoteJidAlt = trim')) {
    fwrite(STDERR, "[textile-lid] provider anchor missing\n");
    exit(1);
}

file_put_contents($servicePath, $service);
file_put_contents($providerPath, $provider);

foreach ([$servicePath, $providerPath] as $path) {
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        file_put_contents($servicePath, $serviceOriginal);
        file_put_contents($providerPath, $providerOriginal);
        fwrite(STDERR, "[textile-lid] syntax check failed; originals restored\n".implode("\n", $output)."\n");
        exit(1);
    }
}

echo "[textile-lid] syntax OK; username/LID contacts enabled for textile bots.\n";
