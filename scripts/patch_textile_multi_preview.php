<?php

/**
 * Final pilot patch: multi-position textile orders render and send one preview
 * per print position. Also keeps pending print positions in pricing before the
 * artwork arrives. Idempotent and syntax-validated for Coolify builds.
 */

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-multi-preview] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-multi-preview] service file could not be read; skipping.\n");
    exit(0);
}

$original = $source;

// Pricing must see positions declared before artwork arrives. Without this,
// "ön sol göğüs ve sırtta büyük" could be priced as a single front print.
if (! str_contains($source, 'PILOT_PENDING_POSITIONS_IN_QUOTE')) {
    $quoteOld = <<<'PHP'
        $positions = array_merge(
            [(string) ($state['position'] ?? '')],
            array_map(
                fn (array $print): string => (string) ($print['position'] ?? ''),
                is_array($state['additional_prints'] ?? null) ? $state['additional_prints'] : [],
            ),
        );
PHP;

    $quoteNew = <<<'PHP'
        // PILOT_PENDING_POSITIONS_IN_QUOTE
        $positions = array_values(array_unique(array_filter(array_merge(
            [(string) ($state['position'] ?? '')],
            array_map(
                fn (array $print): string => (string) ($print['position'] ?? ''),
                is_array($state['additional_prints'] ?? null) ? $state['additional_prints'] : [],
            ),
            array_map(
                fn (mixed $position): string => (string) $position,
                is_array($state['pending_same_artwork_positions'] ?? null)
                    ? $state['pending_same_artwork_positions']
                    : [],
            ),
        ))));
PHP;

    if (! str_contains($source, $quoteOld)) {
        fwrite(STDERR, "[textile-multi-preview] quote anchor missing.\n");
        exit(1);
    }
    $source = str_replace($quoteOld, $quoteNew, $source, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[textile-multi-preview] quote replacement count invalid: {$count}.\n");
        exit(1);
    }
}

// Render every requested print position independently. The customer's existing
// conversation style stays untouched; only image orchestration changes.
if (! str_contains($source, 'PILOT_MULTI_PREVIEW_V1')) {
    $startMarker = "        if ((\$state['logo_received'] ?? false) && (\$state['position'] ?? null) && ! (\$state['mockup_sent'] ?? false)) {\n";
    $endMarker = "        if (\$this->approvalMessage(\$message)) {\n";
    $start = strpos($source, $startMarker);
    $end = $start === false ? false : strpos($source, $endMarker, $start);

    if ($start === false || $end === false || $end <= $start) {
        fwrite(STDERR, "[textile-multi-preview] mockup block anchors missing.\n");
        exit(1);
    }

    $multiBlock = <<<'PHP'
        // PILOT_MULTI_PREVIEW_V1
        if (($state['logo_received'] ?? false) && ($state['position'] ?? null) && ! ($state['mockup_sent'] ?? false)) {
            try {
                $previewJobs = [[
                    'position' => (string) $state['position'],
                    'logo_base64' => (string) $state['logo_base64'],
                ]];

                foreach ((array) ($state['additional_prints'] ?? []) as $print) {
                    if (! is_array($print)) {
                        continue;
                    }
                    $position = trim((string) ($print['position'] ?? ''));
                    $logo = trim((string) ($print['logo_base64'] ?? ''));
                    if ($position === '' || $logo === '') {
                        continue;
                    }
                    $previewJobs[] = [
                        'position' => $position,
                        'logo_base64' => $logo,
                    ];
                }

                // Generate everything first. If one render fails, do not send a
                // partial set of previews to the customer.
                $renderedPreviews = [];
                foreach ($previewJobs as $job) {
                    $renderedPreviews[] = [
                        'position' => $job['position'],
                        'image' => $this->mockupService->create(
                            logoBase64: $job['logo_base64'],
                            position: $job['position'],
                            shirtColor: (string) ($state['color'] ?? 'black'),
                            product: (string) ($state['product'] ?? 'Premium Oversize Tişört'),
                            additionalPrints: [],
                        ),
                    ];
                }

                $totalPreviews = count($renderedPreviews);
                foreach ($renderedPreviews as $index => $preview) {
                    $this->sendImage(
                        $bot,
                        $conversation,
                        $instance,
                        $phone,
                        (string) $preview['image'],
                        $state,
                        (string) $preview['position'],
                        $index + 1,
                        $totalPreviews,
                    );
                }

                $state['mockup_sent'] = true;
                $state = $this->updateSalesStage($state);
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));

                $this->sendText($bot, $conversation, $instance, $phone, $this->mockupMessage($state));
                $this->consumeTrial($bot);
                return true;
            } catch (Throwable $exception) {
                Log::error('TEXTILE MOCKUP FAILED', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'message' => $exception->getMessage(),
                ]);

                $this->sendText($bot, $conversation, $instance, $phone,
                    'Logonuzu ve baskı konumunu aldım. Önizleme hazırlanırken geçici bir sorun oluştu; dosyanız kaybolmadı. Lütfen “önizlemeyi tekrar hazırla” yazın.'
                );
                return true;
            }
        }

PHP;

    $source = substr($source, 0, $start).$multiBlock.substr($source, $end);
}

// Make image messages identify which print area each separate preview belongs to.
if (! str_contains($source, 'PILOT_SEPARATE_PREVIEW_CAPTION')) {
    $methodStart = strpos($source, '    private function sendImage(');
    $methodEndMarker = "    private function conversation(AiBot \$bot, string \$sessionId, string \$phone, array \$payload): ConversationControl\n";
    $methodEnd = $methodStart === false ? false : strpos($source, $methodEndMarker, $methodStart);

    if ($methodStart === false || $methodEnd === false || $methodEnd <= $methodStart) {
        fwrite(STDERR, "[textile-multi-preview] sendImage anchors missing.\n");
        exit(1);
    }

    $sendImage = <<<'PHP'
    private function sendImage(
        AiBot $bot,
        ConversationControl $conversation,
        string $instance,
        string $phone,
        string $mockupBase64,
        array $state,
        ?string $previewPosition = null,
        int $previewIndex = 1,
        int $previewTotal = 1,
    ): void {
        // PILOT_SEPARATE_PREVIEW_CAPTION
        $position = $previewPosition ?: (string) ($state['position'] ?? 'front_center');
        $positionLabel = $this->positionLabel($position);
        $caption = $previewTotal > 1
            ? 'Baskı önizlemeniz hazır ✓ '.$positionLabel.' için ayrı önizleme.'
            : 'Baskı önizlemeniz hazır ✓ Logo orijinal dosyanızdan otomatik yerleştirildi.';
        $filename = $previewTotal > 1
            ? 'baski-onizleme-'.$previewIndex.'-'.$position.'.jpg'
            : 'baski-onizleme.jpg';

        $this->markOutbound($instance, $phone, $caption);
        $send = $this->whatsAppService->sendImage(
            $instance,
            $phone,
            $mockupBase64,
            $filename,
            $caption,
            'image/jpeg',
        );

        ChatMessage::create([
            'user_id' => $bot->user_id,
            'organization_id' => $conversation->organization_id,
            'ai_bot_id' => $bot->id,
            'session_id' => $conversation->session_id,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => '[Baskı önizlemesi] '.$positionLabel,
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => $filename,
            'media_caption' => $caption,
            'whatsapp_message_id' => data_get($send, 'key.id') ?? data_get($send, 'messageId') ?? data_get($send, 'id'),
            'status' => 'sent',
        ]);
    }

PHP;

    $source = substr($source, 0, $methodStart).$sendImage.substr($source, $methodEnd);
}

if ($source === $original) {
    echo "[textile-multi-preview] no changes required.\n";
    exit(0);
}

file_put_contents($path, $source);
$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-multi-preview] syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

echo "[textile-multi-preview] syntax OK; separate previews enabled.\n";
