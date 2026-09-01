<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\Organization;
use App\Models\User;
use App\Services\EvolutionMediaService;
use App\Services\MemoryService;
use App\Services\RealEstateAudioTranscriptionService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOpenAIClient;
use App\Services\RealEstateWhatsAppMessageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RealEstateAudioTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_captures_voice_note_duration_size_and_pending_state(): void
    {
        [$message, $context] = app(RealEstateWhatsAppMessageParser::class)->extract([
            'data' => [
                'message' => [
                    'audioMessage' => [
                        'url' => 'https://example.test/audio',
                        'mimetype' => 'audio/ogg; codecs=opus',
                        'seconds' => 42,
                        'fileLength' => '8192',
                    ],
                ],
            ],
        ], 'emlak-ai-35', 'voice-meta-1');

        $this->assertSame('audio', $context['type']);
        $this->assertSame(42, $context['duration']);
        $this->assertSame(8192, $context['size']);
        $this->assertSame('pending', $context['transcription_status']);
        $this->assertStringContainsString('henüz metne çevrilmedi', $message);
    }

    public function test_voice_note_is_transcribed_only_through_isolated_bot_client(): void
    {
        $bot = $this->seedScope(apiKey: 'sk-test-isolated');

        $media = Mockery::mock(EvolutionMediaService::class);
        $media->shouldReceive('downloadBase64')
            ->once()
            ->with('emlak-ai-35', Mockery::type('array'))
            ->andReturn(base64_encode('OggS fake audio bytes'));

        $client = Mockery::mock(RealEstateOpenAIClient::class);
        $client->shouldReceive('transcribeAudio')
            ->once()
            ->with(
                $bot,
                Mockery::on(fn (string $path): bool => is_file($path)),
                'gpt-4o-mini-transcribe',
                'tr',
                Mockery::type('string'),
            )
            ->andReturn((object) [
                'text' => 'Marmaris Hisarönü’nde 500 metrekare bir arsam var.',
            ]);

        $service = new RealEstateAudioTranscriptionService(
            app(RealEstateIsolationService::class),
            $media,
            $client,
        );

        $result = $service->transcribe(
            bot: $bot,
            instance: 'emlak-ai-35',
            mediaContext: [
                'type' => 'audio',
                'mime_type' => 'audio/ogg; codecs=opus',
                'message_id' => 'voice-1',
                'message_envelope' => ['key' => ['id' => 'voice-1']],
            ],
        );

        $this->assertSame('transcribed', $result['status']);
        $this->assertSame(
            'Marmaris Hisarönü’nde 500 metrekare bir arsam var.',
            $result['text']
        );
        $this->assertSame('gpt-4o-mini-transcribe', $result['model']);
        $this->assertSame('tr', $result['language']);
        $this->assertNotNull($result['transcribed_at']);
    }

    public function test_transcription_refuses_any_non_isolated_instance(): void
    {
        $bot = $this->seedScope(apiKey: 'sk-test-isolated');

        $service = new RealEstateAudioTranscriptionService(
            app(RealEstateIsolationService::class),
            Mockery::mock(EvolutionMediaService::class),
            Mockery::mock(RealEstateOpenAIClient::class),
        );

        $this->expectException(RuntimeException::class);

        $service->transcribe(
            bot: $bot,
            instance: 'gulten-sirketi-1',
            mediaContext: [
                'type' => 'audio',
                'mime_type' => 'audio/ogg',
                'message_envelope' => ['key' => ['id' => 'wrong-instance']],
            ],
        );
    }

    public function test_dedicated_openai_client_never_falls_back_when_bot_key_is_missing(): void
    {
        $bot = $this->seedScope(apiKey: null);
        $path = tempnam(sys_get_temp_dir(), 'audio-test-');
        file_put_contents($path, 'fake audio');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('özel OpenAI API anahtarı');

            app(RealEstateOpenAIClient::class)->transcribeAudio(
                aiBot: $bot,
                filePath: $path,
                model: 'gpt-4o-mini-transcribe',
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_memory_persists_transcript_and_exact_isolated_organization(): void
    {
        $this->seedScope(apiKey: 'sk-test-isolated');

        app(MemoryService::class)->mesajKaydet(
            userId: 40,
            aiBotId: 35,
            sessionId: 'voice-memory-1',
            role: 'user',
            message: 'Bodrum’da satılık bir arsam var.',
            senderType: 'human',
            mediaContext: [
                'type' => 'audio',
                'mime_type' => 'audio/ogg; codecs=opus',
                'filename' => 'Sesli mesaj',
                'duration' => 18,
                'size' => 4096,
                'transcript' => 'Bodrum’da satılık bir arsam var.',
                'transcription_status' => 'transcribed',
                'transcription_model' => 'gpt-4o-mini-transcribe',
                'transcription_language' => 'tr',
                'transcribed_at' => now()->toIso8601String(),
                'message_id' => 'voice-memory-message-1',
            ],
        );

        $message = ChatMessage::query()->sole();

        $this->assertSame(37, (int) $message->organization_id);
        $this->assertSame('audio', $message->message_type);
        $this->assertSame('Bodrum’da satılık bir arsam var.', $message->media_transcript);
        $this->assertSame('transcribed', $message->media_transcription_status);
        $this->assertSame('gpt-4o-mini-transcribe', $message->media_transcription_model);
        $this->assertSame('tr', $message->media_transcription_language);
        $this->assertSame(18, $message->media_duration);
        $this->assertSame(4096, $message->media_size);
        $this->assertNotNull($message->media_transcribed_at);
    }

    private function seedScope(?string $apiKey): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'audio@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'audio-isolated',
            'status' => 'active',
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => $apiKey,
            'status' => 'active',
            'ai_enabled' => true,
            'subscription_status' => 'trial',
            'trial_message_limit' => 1000,
            'trial_messages_used' => 0,
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connecting',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
    }
}
