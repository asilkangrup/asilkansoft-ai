<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RealEstateAudioTranscriptionService
{
    private const MAX_AUDIO_BYTES = 20 * 1024 * 1024;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/webm' => 'webm',
    ];

    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly EvolutionMediaService $mediaService,
        private readonly RealEstateOpenAIClient $openAIClient,
    ) {
    }

    /**
     * @return array{text:?string,status:string,model:?string,language:?string,transcribed_at:?string,bytes:?int}
     */
    public function transcribe(
        AiBot $bot,
        string $instance,
        array $mediaContext,
    ): array {
        $this->assertScope($bot, $instance, $mediaContext);

        $mime = $this->normalizeMime((string) ($mediaContext['mime_type'] ?? ''));
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            return $this->result(status: 'unsupported_format');
        }

        $declaredSize = $this->nullablePositiveInt($mediaContext['size'] ?? null);

        if ($declaredSize !== null && $declaredSize > self::MAX_AUDIO_BYTES) {
            return $this->result(status: 'too_large', bytes: $declaredSize);
        }

        $envelope = $mediaContext['message_envelope'] ?? null;

        if (! is_array($envelope) || $envelope === []) {
            return $this->result(status: 'missing_envelope');
        }

        $model = trim((string) env(
            'REAL_ESTATE_AUDIO_TRANSCRIPTION_MODEL',
            'gpt-4o-mini-transcribe'
        ));
        $language = trim((string) env('REAL_ESTATE_AUDIO_LANGUAGE', 'tr'));
        $startedAt = microtime(true);
        $temporaryPath = null;

        try {
            $base64 = $this->mediaService->downloadBase64(
                instanceName: $instance,
                messageEnvelope: $envelope,
            );

            $binary = base64_decode($base64, true);

            if (! is_string($binary) || $binary === '') {
                return $this->result(status: 'decode_failed', model: $model);
            }

            $bytes = strlen($binary);

            if ($bytes > self::MAX_AUDIO_BYTES) {
                return $this->result(
                    status: 'too_large',
                    model: $model,
                    bytes: $bytes,
                );
            }

            $temporaryPath = $this->temporaryAudioPath($extension);

            if (file_put_contents($temporaryPath, $binary, LOCK_EX) !== $bytes) {
                throw new RuntimeException('Sesli mesaj geçici dosyaya eksiksiz yazılamadı.');
            }

            // Drop the decoded payload before the network request so long voice
            // notes do not remain duplicated in PHP memory while uploading.
            unset($binary, $base64);

            $response = $this->openAIClient->transcribeAudio(
                aiBot: $bot,
                filePath: $temporaryPath,
                model: $model,
                language: $language !== '' ? $language : null,
                prompt: 'Türkiye gayrimenkul görüşmesi. İl, ilçe, mahalle, fiyat, metrekare, ada, parsel, tapu ve imar terimlerini duyulduğu şekilde doğru yaz.',
            );

            $text = trim((string) ($response->text ?? ''));

            if ($text === '') {
                return $this->result(
                    status: 'empty_transcript',
                    model: $model,
                    language: $language !== '' ? $language : null,
                    bytes: $bytes,
                );
            }

            Log::info('REAL ESTATE AUDIO TRANSCRIBED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => $instance,
                'message_id' => $mediaContext['message_id'] ?? null,
                'mime_type' => $mime,
                'bytes' => $bytes,
                'model' => $model,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $this->result(
                status: 'transcribed',
                text: $text,
                model: $model,
                language: $language !== '' ? $language : null,
                transcribedAt: now()->toIso8601String(),
                bytes: $bytes,
            );
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE AUDIO TRANSCRIPTION FAILED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => $instance,
                'message_id' => $mediaContext['message_id'] ?? null,
                'mime_type' => $mime,
                'model' => $model,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_class' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            report($exception);

            return $this->result(
                status: 'failed',
                model: $model,
                language: $language !== '' ? $language : null,
            );
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function assertScope(
        AiBot $bot,
        string $instance,
        array $mediaContext,
    ): void {
        if (! $this->isolation->supportsProductionBot($bot)) {
            throw new RuntimeException('Ses transkripsiyonu kapsam dışı bot için kullanılamaz.');
        }

        if ($instance !== RealEstateIsolationService::INSTANCE) {
            throw new RuntimeException('Ses transkripsiyonu yalnız emlak-ai-35 instance için kullanılabilir.');
        }

        if (($mediaContext['type'] ?? null) !== 'audio') {
            throw new RuntimeException('Ses transkripsiyonuna ses olmayan medya gönderildi.');
        }
    }

    private function normalizeMime(string $mime): string
    {
        return strtolower(trim(explode(';', $mime, 2)[0] ?? ''));
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function temporaryAudioPath(string $extension): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'emlak-audio-');

        if ($basePath === false) {
            throw new RuntimeException('Ses transkripsiyonu için geçici dosya oluşturulamadı.');
        }

        $path = $basePath.'.'.$extension;

        if (! @rename($basePath, $path)) {
            @unlink($basePath);
            throw new RuntimeException('Ses transkripsiyonu geçici dosyası hazırlanamadı.');
        }

        return $path;
    }

    /**
     * @return array{text:?string,status:string,model:?string,language:?string,transcribed_at:?string,bytes:?int}
     */
    private function result(
        string $status,
        ?string $text = null,
        ?string $model = null,
        ?string $language = null,
        ?string $transcribedAt = null,
        ?int $bytes = null,
    ): array {
        return [
            'text' => $text,
            'status' => $status,
            'model' => $model,
            'language' => $language,
            'transcribed_at' => $transcribedAt,
            'bytes' => $bytes,
        ];
    }
}
