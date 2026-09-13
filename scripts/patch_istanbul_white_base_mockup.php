<?php

$inboundPath = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';
$mockupPath = dirname(__DIR__).'/app/Services/Textile/TextileMockupService.php';

$inbound = file_get_contents($inboundPath);
$mockup = file_get_contents($mockupPath);
$inboundOriginal = $inbound;
$mockupOriginal = $mockup;

$oldReady = "                    readyTemplateOnly: ((int) $bot->id === 51 && (int) $bot->user_id === 46),";
$newReady = "                    readyTemplateOnly: false, // ISTANBUL_WHITE_BASE_MOCKUP_V1";
if (str_contains($inbound, $oldReady)) {
    $inbound = str_replace($oldReady, $newReady, $inbound, $count);
}

$oldOversize = <<<'PHP'
        } elseif (str_contains($normalized, 'oversize')) {
            $kind = 'oversize';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'oversize-white-studio.jpg'
                : 'oversize-black-studio.jpg';
PHP;
$newOversize = <<<'PHP'
        } elseif (str_contains($normalized, 'oversize')) {
            $kind = 'oversize';
            // Coloured oversize mockups start from the clean white garment photo;
            // black stays on the dedicated black photo. This prevents black sleeves,
            // collar blocks and lower-garment spill when tinting colours.
            $templateColor = $shirtColor === 'black' ? 'black' : 'white';
            $filename = $templateColor === 'white'
                ? 'oversize-white-studio.jpg'
                : 'oversize-black-studio.jpg';
PHP;
if (str_contains($mockup, $oldOversize)) {
    $mockup = str_replace($oldOversize, $newOversize, $mockup, $count2);
}

file_put_contents($inboundPath, $inbound);
file_put_contents($mockupPath, $mockup);

foreach ([$inboundPath, $mockupPath] as $path) {
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $out, $code);
    if ($code !== 0) {
        file_put_contents($inboundPath, $inboundOriginal);
        file_put_contents($mockupPath, $mockupOriginal);
        fwrite(STDERR, implode("\n", $out)."\n");
        exit(1);
    }
}

echo "[istanbul-white-base] OK\n";
