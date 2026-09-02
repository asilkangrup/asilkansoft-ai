<?php

namespace Tests\Feature;

use App\Jobs\ProcessRealEstateMediaBatch;
use App\Jobs\ProcessRealEstateWhatsAppWebhook;
use App\Services\RealEstateQueueTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealEstateQueueThroughputTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_and_media_jobs_for_same_chat_share_one_overlap_key(): void
    {
        $remoteJid = '126817583255713@lid';
        $hash = hash('sha256', strtolower($remoteJid));

        $textJob = new ProcessRealEstateWhatsAppWebhook([
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => [
                    'remoteJid' => $remoteJid,
                    'id' => 'message-1',
                ],
            ],
        ]);
        $mediaJob = new ProcessRealEstateMediaBatch(
            'real-estate-media-batch:'.$hash,
            'generation-1',
        );

        $this->assertSame('real-estate-chat:'.$hash, $textJob->overlapKey());
        $this->assertSame($textJob->overlapKey(), $mediaJob->overlapKey());
        $this->assertSame(120, $textJob->tries);
        $this->assertSame(120, $mediaJob->tries);
        $this->assertInstanceOf(WithoutOverlapping::class, $textJob->middleware()[0]);
        $this->assertInstanceOf(WithoutOverlapping::class, $mediaJob->middleware()[0]);
    }

    public function test_different_whatsapp_chats_can_run_in_parallel(): void
    {
        $first = new ProcessRealEstateWhatsAppWebhook([
            'data' => ['key' => ['remoteJid' => '905550000001@s.whatsapp.net']],
        ]);
        $second = new ProcessRealEstateWhatsAppWebhook([
            'data' => ['key' => ['remoteJid' => '905550000002@s.whatsapp.net']],
        ]);

        $this->assertNotSame($first->overlapKey(), $second->overlapKey());
    }

    public function test_queue_telemetry_counts_only_the_isolated_real_estate_queue(): void
    {
        $now = now()->timestamp;

        DB::table('jobs')->insert([
            [
                'queue' => 'real-estate',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now - 45,
                'created_at' => $now - 45,
            ],
            [
                'queue' => 'real-estate',
                'payload' => '{}',
                'attempts' => 1,
                'reserved_at' => $now - 2,
                'available_at' => $now - 10,
                'created_at' => $now - 10,
            ],
            [
                'queue' => 'real-estate',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now + 15,
                'created_at' => $now,
            ],
            [
                'queue' => 'default',
                'payload' => '{"old_account":true}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now - 300,
                'created_at' => $now - 300,
            ],
        ]);

        $snapshot = app(RealEstateQueueTelemetryService::class)->snapshot();

        $this->assertTrue($snapshot['ready']);
        $this->assertSame('real-estate', $snapshot['queue']);
        $this->assertSame(3, $snapshot['pending_jobs']);
        $this->assertSame(1, $snapshot['available_jobs']);
        $this->assertSame(1, $snapshot['reserved_jobs']);
        $this->assertSame(1, $snapshot['delayed_jobs']);
        $this->assertGreaterThanOrEqual(45, $snapshot['oldest_wait_seconds']);
        $this->assertSame('busy', $snapshot['state']);
        $this->assertSame(4, $snapshot['target_parallel_workers']);
        $this->assertFalse($snapshot['contains_customer_payload']);
        $this->assertSame(40, $snapshot['scope']['user_id']);
        $this->assertSame(37, $snapshot['scope']['organization_id']);
        $this->assertSame(35, $snapshot['scope']['bot_id']);
        $this->assertSame('emlak-ai-35', $snapshot['scope']['instance']);
    }

    public function test_queue_health_command_never_prints_job_payloads(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'real-estate',
            'payload' => '{"secret_customer_text":"do not print me"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('real-estate:queue-health --json')
            ->expectsOutputToContain('"queue":"real-estate"')
            ->doesntExpectOutputToContain('do not print me')
            ->assertSuccessful();
    }
}
