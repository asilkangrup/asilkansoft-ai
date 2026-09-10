<?php

/**
 * Idempotent build patch for the textile WhatsApp pilot.
 *
 * This keeps the production behaviour stable across Coolify/Railpack rebuilds
 * while the pilot is being hardened. Every replacement is guarded so the
 * script can be run repeatedly without duplicating code.
 */

$path = dirname(__DIR__).'/app/Services/Textile/TextileWhatsAppInboundService.php';

if (! is_file($path)) {
    fwrite(STDERR, "[textile-pilot] service file not found; skipping.\n");
    exit(0);
}

$source = file_get_contents($path);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "[textile-pilot] service file could not be read; skipping.\n");
    exit(0);
}

$original = $source;

$replaceOnce = static function (string &$text, string $old, string $new, string $label): bool {
    if (str_contains($text, $new)) {
        echo "[textile-pilot] already applied: {$label}\n";
        return true;
    }

    if (! str_contains($text, $old)) {
        fwrite(STDERR, "[textile-pilot] anchor missing: {$label}\n");
        return false;
    }

    $text = preg_replace('/'.preg_quote($old, '/').'/', str_replace(['\\', '$'], ['\\\\', '\\$'], $new), $text, 1) ?? $text;
    echo "[textile-pilot] applied: {$label}\n";
    return true;
};

// Understand plain “tişört”, not only oversize/regular/polo keywords.
if (! str_contains($source, "'tişört' => ['Regular Fit Tişört', 'shirt']")) {
    $replaceOnce(
        $source,
        "            'heavy' => ['Heavy Cotton Tişört', 'shirt'],\n",
        "            'heavy' => ['Heavy Cotton Tişört', 'shirt'],\n            'tişört' => ['Regular Fit Tişört', 'shirt'],\n            'tisort' => ['Regular Fit Tişört', 'shirt'],\n",
        'plain-shirt-product'
    );
}

// Accept natural quantity phrasing such as “50 siyah tişört”.
if (! str_contains($source, 'leading quantity without adet/tane')) {
    $marker = "        \$hasExplicitQuantity = (bool) preg_match(";
    $start = strpos($source, $marker);
    if ($start !== false) {
        $end = strpos($source, ";\n", $start);
        if ($end !== false) {
            $end += 2;
            $insert = <<<'PHP'
        // leading quantity without adet/tane, e.g. 50 siyah tişört
        if (! $hasExplicitQuantity && $detectedProduct !== null && preg_match('/^\\s*([1-9][0-9]{0,4})\\b/u', $lower, $quantityMatch)) {
            $hasExplicitQuantity = true;
        }
PHP;
            $source = substr($source, 0, $end).$insert."\n".substr($source, $end);
            echo "[textile-pilot] applied: leading-quantity\n";
        }
    }
}

// Natural Turkish print-position variants used in real WhatsApp chats.
$positionReplacements = [
    "            'ön sol göğse' => 'left_chest', 'on sol goguse' => 'left_chest'," => "            'ön sol göğüste' => 'left_chest', 'on sol goguste' => 'left_chest',\n            'ön sol göğse' => 'left_chest', 'on sol goguse' => 'left_chest',",
    "            'sol göğse' => 'left_chest', 'sol goguse' => 'left_chest'," => "            'sol göğüste' => 'left_chest', 'sol goguste' => 'left_chest',\n            'sol göğse' => 'left_chest', 'sol goguse' => 'left_chest',",
    "            'ön sağ göğse' => 'right_chest', 'on sag goguse' => 'right_chest'," => "            'ön sağ göğüste' => 'right_chest', 'on sag goguste' => 'right_chest',\n            'ön sağ göğse' => 'right_chest', 'on sag goguse' => 'right_chest',",
    "            'sağ göğse' => 'right_chest', 'sag goguse' => 'right_chest'," => "            'sağ göğüste' => 'right_chest', 'sag goguste' => 'right_chest',\n            'sağ göğse' => 'right_chest', 'sag goguse' => 'right_chest',",
    "            'sol kol' => 'left_sleeve', 'sağ kol' => 'right_sleeve', 'sag kol' => 'right_sleeve'," => "            'sol kolda' => 'left_sleeve', 'sağ kolda' => 'right_sleeve', 'sag kolda' => 'right_sleeve',\n            'sol kol' => 'left_sleeve', 'sağ kol' => 'right_sleeve', 'sag kol' => 'right_sleeve',",
    "            'sırta' => 'back_large', 'sirta' => 'back_large'," => "            'sırtta' => 'back_large', 'sirtta' => 'back_large',\n            'sırta' => 'back_large', 'sirta' => 'back_large',",
    "            'arkaya' => 'back_large', 'sırt' => 'back_large', 'sirt' => 'back_large', 'arka' => 'back_large'," => "            'arkada' => 'back_large', 'arkaya' => 'back_large', 'sırt' => 'back_large', 'sirt' => 'back_large', 'arka' => 'back_large',",
    "            'öne' => 'front_center', 'one' => 'front_center'," => "            'önde' => 'front_center', 'onde' => 'front_center',\n            'öne' => 'front_center', 'one' => 'front_center',",
];

foreach ($positionReplacements as $old => $new) {
    if (! str_contains($source, $new) && str_contains($source, $old)) {
        $source = str_replace($old, $new, $source);
    }
}

// Give fallback responses access to the company's verified profile.
$source = str_replace(
    'return $this->naturalFallback($message, $state);',
    'return $this->naturalFallback($bot, $message, $state);',
    $source
);
$source = str_replace(
    'private function naturalFallback(string $message, array $state): string',
    'private function naturalFallback(AiBot $bot, string $message, array $state): string',
    $source
);

if (! str_contains($source, 'private function firmKnowledgeReply(')) {
    $anchor = "    private function liveConversationInstructions(array \$state): string\n";
    $helpers = <<<'PHP'
    private function firmKnowledgeReply(AiBot $bot, string $message): ?string
    {
        $normalized = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], trim($message)));
        $rules = (string) $bot->company_rules;

        $field = function (string $label) use ($rules): ?string {
            if (preg_match('/^\s*'.preg_quote($label, '/').'\s*:\s*(.+)$/mi', $rules, $match)) {
                $value = trim((string) ($match[1] ?? ''));
                return $value !== '' ? $value : null;
            }
            return null;
        };

        $intents = [
            ['/(?:yeriniz|adres|konum|nerede|nerde|atölye|atolye)/u', 'Adres ve ziyaret bilgisi'],
            ['/(?:çalışma saat|calisma saat|kaçta aç|kacta ac|kaçta kapa|kacta kapa)/u', 'Çalışma ve ziyaret düzeni'],
            ['/(?:minimum|en az|kaç adet.*baş|kac adet.*bas)/u', 'Minimum sipariş kuralları'],
            ['/(?:kaç gün|kac gun|ne zaman hazır|ne zaman hazir|üretim süresi|uretim suresi|termin|aynı gün|ayni gun|acil)/u', 'Üretim süresi'],
            ['/(?:kargo|teslimat)/u', 'Kargo ve teslimat'],
            ['/(?:kumaş|kumas|gramaj|pamuk)/u', 'Kumaş kalitesi'],
            ['/(?:dtg|dtf|transfer|dijital baskı|dijital baski|baskı yöntemi|baski yontemi|nakış|nakis)/u', 'Baskı yöntemleri'],
            ['/(?:beden|kaç xl|kac xl)/u', 'Beden aralığı'],
            ['/(?:instagram|web sitesi|internet sitesi|site adres)/u', 'Web sitesi ve sosyal medya'],
            ['/(?:telefon|iletişim numarası|iletisim numarasi)/u', 'Firma telefonu'],
            ['/(?:iade|değişim|degisim)/u', 'Değişim ve satış sonrası'],
            ['/(?:ödeme|odeme|havale|kapıda ödeme|kapida odeme)/u', 'Ödeme'],
        ];

        foreach ($intents as [$pattern, $label]) {
            if (preg_match($pattern, $normalized) === 1) {
                $value = $field($label);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function hasActiveOrder(array $state): bool
    {
        return (bool) (($state['product'] ?? null)
            || ($state['quantity'] ?? null)
            || ($state['color'] ?? null)
            || ($state['position'] ?? null)
            || ($state['logo_received'] ?? false));
    }

    private function updateSalesStage(array $state): array
    {
        if (! $this->hasActiveOrder($state)) {
            $state['sales_intent'] = false;
            $state['sales_stage'] = 'browsing';
            return $state;
        }

        $state['sales_intent'] = true;
        if ($state['approved'] ?? false) {
            $state['sales_stage'] = 'ready_for_handoff';
        } elseif ($state['mockup_sent'] ?? false) {
            $state['sales_stage'] = 'mockup_sent';
        } elseif ($state['logo_received'] ?? false) {
            $state['sales_stage'] = 'artwork_received';
        } elseif (($state['product'] ?? null) && ($state['quantity'] ?? null) && ($state['color'] ?? null) && ($state['position'] ?? null)) {
            $state['sales_stage'] = 'awaiting_artwork';
        } else {
            $state['sales_stage'] = 'collecting_details';
        }

        return $state;
    }

PHP;

    if (str_contains($source, $anchor)) {
        $source = str_replace($anchor, $helpers.$anchor, $source, $count);
        echo "[textile-pilot] applied: firm knowledge + sales stage helpers\n";
    }
}

// Business questions must be answered directly before the LLM can fall back
// to a generic response, while preserving the current order state.
if (! str_contains($source, '$knownFirmAnswer = $this->firmKnowledgeReply')) {
    $old = "    ): string {\n        \$history = ChatMessage::query()";
    $new = <<<'PHP'
    ): string {
        $knownFirmAnswer = $this->firmKnowledgeReply($bot, $message);
        if ($knownFirmAnswer !== null) {
            if ($this->hasActiveOrder($state) && ! ($state['approved'] ?? false) && ! ($state['mockup_sent'] ?? false)) {
                $next = $this->nextQuestion($state);
                if ($next !== '') {
                    $knownFirmAnswer .= "\n\n".$next;
                }
            }
            return $knownFirmAnswer;
        }

        $history = ChatMessage::query()
PHP;
    $replaceOnce($source, $old, $new, 'firm-direct-answer');
}

// More conversational memory for the pilot.
$source = str_replace("            ->limit(8)\n            ->get(['role', 'message'])", "            ->limit(50)\n            ->get(['role', 'message'])", $source);
$source = str_replace("            ->limit(30)\n            ->get(['role', 'message'])", "            ->limit(50)\n            ->get(['role', 'message'])", $source);

// Replace the old generic fallback with a state-aware one.
$fallbackStart = strpos($source, "    private function naturalFallback(AiBot \$bot, string \$message, array \$state): string\n");
$fallbackEnd = $fallbackStart === false ? false : strpos($source, "    private function nextQuestion(array \$state): string\n", $fallbackStart);
if ($fallbackStart !== false && $fallbackEnd !== false) {
    $fallback = <<<'PHP'
    private function naturalFallback(AiBot $bot, string $message, array $state): string
    {
        $normalized = Str::lower(trim($message));

        if (($known = $this->firmKnowledgeReply($bot, $message)) !== null) {
            return $this->hasActiveOrder($state) && ! ($state['approved'] ?? false) && ! ($state['mockup_sent'] ?? false)
                ? $known."\n\n".$this->nextQuestion($state)
                : $known;
        }

        if ($this->greetingMessage($message)) {
            return ($state['approved'] ?? false)
                ? 'Merhaba 👋 Mevcut siparişiniz onaylandı. Yeni bir ürün veya tasarım için de yardımcı olabilirim.'
                : $this->nextQuestion($state);
        }

        if (preg_match('/(teşekkür|tesekkur|sağ ol|sag ol)/u', $normalized)) {
            return 'Rica ederim. İsterseniz kaldığımız yerden devam edebiliriz.';
        }

        if ($this->hasActiveOrder($state)) {
            return $this->nextQuestion($state);
        }

        if (preg_match('/(tişört|tisort).*(bastır|bastir|baskı|baski)|(bastır|bastir|baskı|baski).*(tişört|tisort)/u', $normalized)) {
            return 'Tabii, yapabiliriz. Nasıl bir tişört düşünüyorsunuz: regular, oversize veya polo yaka?';
        }

        return 'Tabii. Ne bastırmak istediğinizi kısaca yazın, oradan birlikte ilerleyelim.';
    }

PHP;
    $source = substr($source, 0, $fallbackStart).$fallback.substr($source, $fallbackEnd);
}

// Ask only the next genuinely missing order detail.
$questionStart = strpos($source, "    private function nextQuestion(array \$state): string\n");
$questionEnd = $questionStart === false ? false : strpos($source, "    private function mockupMessage(array \$state): string\n", $questionStart);
if ($questionStart !== false && $questionEnd !== false) {
    $nextQuestion = <<<'PHP'
    private function nextQuestion(array $state): string
    {
        if ($state['awaiting_order_item_selection'] ?? false) {
            return 'Belgedeki ürünlerden hangisinin baskı önizlemesini önce hazırlayalım?';
        }
        if ($state['awaiting_additional_artwork_choice'] ?? false) {
            return 'Bu ikinci baskıda aynı görseli mi kullanalım, farklı bir görsel mi göndereceksiniz?';
        }
        if ($state['awaiting_additional_image_position'] ?? null) {
            return 'Tamamdır, ikinci baskıda kullanacağınız farklı görseli gönderebilirsiniz.';
        }
        if ($state['awaiting_uploaded_artwork_position'] ?? false) {
            return 'Bu yeni görseli nereye basalım? Örneğin sol göğüs, ön, sırt ya da kol.';
        }
        if (! ($state['product'] ?? null)) {
            return $this->hasActiveOrder($state)
                ? 'Hangi ürüne baskı yapacağız?'
                : 'Ne üzerine baskı düşünüyorsunuz? Tişört, sweatshirt veya şapka gibi ürünü yazmanız yeterli.';
        }
        if (! ($state['quantity'] ?? null)) {
            return 'Kaç adet düşünüyorsunuz?';
        }
        if (! ($state['color'] ?? null)) {
            return 'Renk ne olsun?';
        }
        if (! ($state['position'] ?? null)) {
            return 'Baskı nereye gelsin? Örneğin sol göğüs, ön orta, ön büyük veya sırt.';
        }
        if (! ($state['logo_received'] ?? false)) {
            return 'Tamamdır. Şimdi baskıda kullanacağınız logo veya görseli gönderebilirsiniz.';
        }
        if ($state['mockup_sent'] ?? false) {
            return 'Önizlemede değiştirmek istediğiniz bir yer varsa söyleyin. İsterseniz aynı siparişe ikinci bir baskı da ekleyebiliriz.';
        }

        return 'Görselinizi aldım, önizlemeyi hazırlıyorum.';
    }

PHP;
    $source = substr($source, 0, $questionStart).$nextQuestion.substr($source, $questionEnd);
}

// Track sales intent internally for the future WhatsApp-group handoff.
$cacheLine = "        Cache::store('database')->put(\$stateKey, \$state, now()->addHours(self::STATE_TTL_HOURS));";
$firstCache = strpos($source, $cacheLine);
if ($firstCache !== false) {
    $prefix = "        \$state = \$this->updateSalesStage(\$state);\n\n";
    if (substr($source, max(0, $firstCache - strlen($prefix)), strlen($prefix)) !== $prefix) {
        $source = substr($source, 0, $firstCache).$prefix.substr($source, $firstCache);
    }
}

$source = str_replace(
    "                \$state['mockup_sent'] = true;\n                Cache::store('database')->put(\$stateKey, \$state, now()->addHours(self::STATE_TTL_HOURS));",
    "                \$state['mockup_sent'] = true;\n                \$state = \$this->updateSalesStage(\$state);\n                Cache::store('database')->put(\$stateKey, \$state, now()->addHours(self::STATE_TTL_HOURS));",
    $source
);
$source = str_replace(
    "            \$state['approved'] = true;\n            \$state['order_id'] ??=",
    "            \$state['approved'] = true;\n            \$state = \$this->updateSalesStage(\$state);\n            \$state['order_id'] ??=",
    $source
);

if (! str_contains($source, "            'sales_intent' => false,")) {
    $replaceOnce(
        $source,
        "            'awaiting_order_item_selection' => false,",
        "            'awaiting_order_item_selection' => false,\n            'sales_intent' => false,\n            'sales_stage' => 'browsing',",
        'sales-state-defaults'
    );
}

if (! str_contains($source, "            'sales_intent',")) {
    $replaceOnce(
        $source,
        "            'awaiting_order_item_selection',",
        "            'awaiting_order_item_selection',\n            'sales_intent',\n            'sales_stage',",
        'sales-state-progress'
    );
}

if ($source === $original) {
    echo "[textile-pilot] no changes required.\n";
    exit(0);
}

$backup = $path.'.pre-pilot-build';
file_put_contents($backup, $original);
file_put_contents($path, $source);

$command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    file_put_contents($path, $original);
    fwrite(STDERR, "[textile-pilot] syntax check failed; original restored.\n".implode("\n", $output)."\n");
    exit(1);
}

@unlink($backup);
echo "[textile-pilot] syntax OK; pilot patch ready.\n";
