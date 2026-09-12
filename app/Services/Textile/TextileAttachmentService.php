<?php

namespace App\Services\Textile;

use App\Models\AiBot;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class TextileAttachmentService
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    public function inspect(
        string $base64,
        string $mime,
        string $filename,
        ?AiBot $bot = null,
    ): array {
        $base64 = $this->cleanBase64($base64);
        $bytes = base64_decode($base64, true);

        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('Dosya açılamadı veya 15 MB güvenli sınırını aşıyor.');
        }

        $mime = strtolower(trim($mime)) ?: 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');
        $input = $isImage
            ? [
                ['type' => 'input_text', 'text' => $this->analysisPrompt()],
                [
                    'type' => 'input_image',
                    'image_url' => "data:{$mime};base64,{$base64}",
                    'detail' => 'high',
                ],
            ]
            : [
                ['type' => 'input_text', 'text' => $this->analysisPrompt()],
                [
                    'type' => 'input_file',
                    'filename' => $filename !== '' ? $filename : 'belge',
                    'file_data' => "data:{$mime};base64,{$base64}",
                ],
            ];

        try {
            $model = trim((string) ($bot?->openai_model ?: 'gpt-5-mini'));
            $request = [
                'model' => $model,
                'instructions' => 'Yalnızca geçerli JSON döndür. Açıklama veya markdown ekleme.',
                'input' => [[
                    'role' => 'user',
                    'content' => $input,
                ]],
                'max_output_tokens' => 1000,
            ];
            if (str_starts_with($model, 'gpt-5') || preg_match('/^o\d/i', $model)) {
                $request['reasoning'] = ['effort' => 'low'];
            }

            $response = OpenAI::responses()->create($request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'textile_attachment_analysis',
                aiBot: $bot,
                meta: ['mime' => $mime, 'filename' => $filename],
            );

            $result = $this->decodeJson((string) ($response->outputText ?? ''));
        } catch (Throwable $exception) {
            Log::warning('TEXTILE ATTACHMENT ANALYSIS FAILED', [
                'ai_bot_id' => $bot?->id,
                'mime' => $mime,
                'filename' => $filename,
                'message' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Dosya yapay zekâ ile analiz edilemedi.', previous: $exception);
        }

        $result['kind'] = in_array(($result['kind'] ?? ''), [
            'artwork', 'printed_product', 'order_document', 'other',
        ], true) ? $result['kind'] : ($isImage ? 'artwork' : 'order_document');
        $result['summary'] = trim((string) ($result['summary'] ?? ''));
        $result['order_text'] = trim((string) ($result['order_text'] ?? ''));
        $result['order_items'] = is_array($result['order_items'] ?? null)
            ? array_slice($result['order_items'], 0, 20)
            : [];

        // Hazır mockup / basılı ürün görsellerinde model çoğu zaman ürün,
        // renk ve baskı konumlarını summary alanında doğru görüyor ancak
        // order_text boş kalıyordu. Bu görsel bilgileri mevcut sipariş
        // parser'ına aktar ki fiyat akışı yalnız eksik bilgiyi sorsun.
        if (
            $isImage
            && $result['kind'] === 'printed_product'
            && $result['order_text'] === ''
            && $result['summary'] !== ''
        ) {
            $result['order_text'] = $result['summary'];
        }

        if ($isImage) {
            $result['artwork_base64'] = $base64;
            if (
                $result['kind'] === 'printed_product'
                || (($result['contains_printable_artwork'] ?? false) && is_array($result['artwork_bbox'] ?? null))
            ) {
                $bbox = is_array($result['artwork_bbox'] ?? null)
                    ? $result['artwork_bbox']
                    : [];
                $result['artwork_base64'] = $this->extractArtwork($bytes, $bbox);
            }
        }

        return $result;
    }

    private function analysisPrompt(): string
    {
        return <<<'PROMPT'
Bu dosyayı baskılı tekstil siparişi için incele.

Amaç:
1. Dosyada tişört, sweatshirt, şapka veya başka bir fiziksel ürün görünmüyorsa; logo, amblem, yazı ve renkli/düz arka plan içeren görselin tamamı kind="artwork" sayılır. Arka planı veya yazının herhangi bir bölümünü kırpma. artwork_bbox bu durumda tüm görseli kapsasın.
2. Yalnızca görselde gerçekten fiziksel bir tişört, sweatshirt, şapka veya başka bir tekstil ürünü görünüyorsa kind="printed_product". Görsel WhatsApp ekran görüntüsü veya hazırlanmış ön/arka ürün mockup'ı olsa bile ürünü, ürün rengini ve görünen baskı konumlarını tespit et. summary alanını sipariş parser'ının anlayacağı net Türkçe ile yaz; örneğin: "Siyah tişört, ön sol göğüste küçük baskı ve sırtta büyük baskı." Birden fazla ürün/renk varsa order_items dizisine ayrı ayrı yaz. artwork_bbox yalnızca üründeki basılı tasarımın tümünü; simge, yazı ve tasarıma ait arka planla birlikte yüzde 0-100 koordinatlarıyla ver: {"x":...,"y":...,"width":...,"height":...}. Ürünün tamamını veya sohbet ekranını değil, basılı tasarımı seç.
3. Dosyada sipariş bilgileri, proforma talebi, adet, ürün, renk, beden, baskı konumu gibi yazılar varsa kind="order_document". Metni order_text alanına eksiksiz ve kısa aktar.
4. Birden fazla ürün/satır varsa order_items dizisine ayrı nesneler olarak koy. Her nesne mümkünse product, quantity, color, sizes, print_positions, note alanlarını içersin.
5. Hem sipariş bilgisi hem baskılı ürün görseli varsa kind mutlaka "printed_product" olsun; ayrıca order_text ve order_items alanlarını da doldur. Görselden açıkça görülen ürün, renk ve baskı konumlarını order_text içine de ekle. Yazılı olmayan santimetre ölçüsünü uydurma; ölçü görünmüyorsa boş bırak.
6. Hazır tasarım/mockup üzerinde ön ve arka görünüm birlikte varsa bunu iki ayrı baskı konumu olarak belirt. Küçük göğüs baskısını "ön sol göğüs" veya "ön sağ göğüs", büyük ön baskıyı "ön büyük", büyük arka baskıyı "sırtta büyük" şeklinde yaz. Emin olmadığın sağ/sol tarafı uydurma; yalnız "ön göğüs" diye belirt.
7. İnsan yüzünü veya kişisel fotoğrafı logo olarak yeniden üretme. Sadece müşterinin istediği mevcut baskıyı tespit et.
8. Emin olmadığın bilgiyi uydurma; null veya boş bırak.

Şu JSON şemasına uy:
{
  "kind":"artwork|printed_product|order_document|other",
  "summary":"Türkçe kısa özet",
  "order_text":"okunan veya görselden güvenle çıkarılan sipariş bilgisi",
  "order_items":[],
  "artwork_bbox":{"x":0,"y":0,"width":100,"height":100},
  "contains_printable_artwork":true,
  "needs_human":false
}
PROMPT;
    }

    private function decodeJson(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^\x60\x60\x60(?:json)?\s*|\s*\x60\x60\x60$/iu', '', $text) ?? $text;
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Dosya analizi geçerli JSON üretmedi.');
        }

        return $decoded;
    }

    public function extractArtwork(string $bytes, array $bbox, bool $robustBackground = false): string
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return base64_encode($bytes);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $x = $this->percent($bbox['x'] ?? 0);
        $y = $this->percent($bbox['y'] ?? 0);
        $width = max(5.0, $this->percent($bbox['width'] ?? 100));
        $height = max(5.0, $this->percent($bbox['height'] ?? 100));

        $left = max(0, (int) floor($sourceWidth * $x / 100));
        $top = max(0, (int) floor($sourceHeight * $y / 100));
        $cropWidth = min($sourceWidth - $left, max(1, (int) ceil($sourceWidth * $width / 100)));
        $cropHeight = min($sourceHeight - $top, max(1, (int) ceil($sourceHeight * $height / 100)));

        $crop = imagecrop($source, [
            'x' => $left,
            'y' => $top,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);
        imagedestroy($source);

        if ($crop === false) {
            return base64_encode($bytes);
        }

        $this->removeFlatGarmentBackground($crop, $robustBackground);

        ob_start();
        imagepng($crop, null, 6);
        $png = ob_get_clean();
        imagedestroy($crop);

        return is_string($png) && $png !== '' ? base64_encode($png) : base64_encode($bytes);
    }

    private function removeFlatGarmentBackground(mixed $image, bool $robustBackground = false): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 8 || $height < 8) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $samples = [
            imagecolorat($image, 1, 1),
            imagecolorat($image, $width - 2, 1),
            imagecolorat($image, 1, $height - 2),
            imagecolorat($image, $width - 2, $height - 2),
        ];
        $red = $green = $blue = 0;
        foreach ($samples as $pixel) {
            $red += ($pixel >> 16) & 0xff;
            $green += ($pixel >> 8) & 0xff;
            $blue += $pixel & 0xff;
        }
        $red = (int) round($red / count($samples));
        $green = (int) round($green / count($samples));
        $blue = (int) round($blue / count($samples));

        if ($robustBackground) {
            $rs = $gs = $bs = [];
            foreach ($samples as $pixel) {
                $rs[] = ($pixel >> 16) & 0xff;
                $gs[] = ($pixel >> 8) & 0xff;
                $bs[] = $pixel & 0xff;
            }
            sort($rs); sort($gs); sort($bs);
            $red = $rs[1]; $green = $gs[1]; $blue = $bs[1];
        }

        for ($py = 0; $py < $height; $py++) {
            for ($px = 0; $px < $width; $px++) {
                $pixel = imagecolorat($image, $px, $py);
                $pr = ($pixel >> 16) & 0xff;
                $pg = ($pixel >> 8) & 0xff;
                $pb = $pixel & 0xff;
                $distance = sqrt((($pr - $red) ** 2) + (($pg - $green) ** 2) + (($pb - $blue) ** 2));
                if ($distance >= 78) {
                    continue;
                }
                $alpha = $distance <= 32 ? 127 : (int) round(127 * (78 - $distance) / 46);
                imagesetpixel($image, $px, $py, imagecolorallocatealpha($image, $pr, $pg, $pb, $alpha));
            }
        }
    }

    private function percent(mixed $value): float
    {
        return max(0.0, min(100.0, is_numeric($value) ? (float) $value : 0.0));
    }

    private function cleanBase64(string $base64): string
    {
        $base64 = trim($base64);
        if (str_contains($base64, ';base64,')) {
            return explode(';base64,', $base64, 2)[1] ?? '';
        }
        return $base64;
    }
}
