<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\RealEstatePrivateMedia;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RealEstatePrivateMediaController extends Controller
{
    public function showInbound(int $message): Response|BinaryFileResponse
    {
        $this->authorizeIsolatedOperator();

        $chatMessage = ChatMessage::query()
            ->whereKey($message)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->whereIn('message_type', ['image', 'document'])
            ->firstOrFail();

        $marker = trim((string) $chatMessage->media_url);
        abort_unless(str_starts_with($marker, 'private:real-estate-inbound/'.$chatMessage->id.'/'), 404);
        $path = substr($marker, strlen('private:'));
        $mime = (string) ($chatMessage->media_mime_type ?: 'application/octet-stream');

        $blob = RealEstatePrivateMedia::query()
            ->isolatedProduction()
            ->where('chat_message_id', $chatMessage->id)
            ->first();

        if ($blob) {
            return $this->privateBytes((string) $blob->content_base64, $mime);
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        return $this->privateFile($path, $mime);
    }

    public function show(int $profile, string $media): Response|BinaryFileResponse
    {
        $this->authorizeIsolatedOperator();

        $profileModel = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey($profile)
            ->firstOrFail();
        $data = is_array($profileModel->data) ? $profileModel->data : [];
        $record = collect(is_array($data['manual_media'] ?? null) ? $data['manual_media'] : [])
            ->first(fn ($item): bool => is_array($item) && hash_equals((string) ($item['id'] ?? ''), $media));

        abort_unless(is_array($record), 404);

        $mime = (string) ($record['mime_type'] ?? 'application/octet-stream');
        $blob = RealEstatePrivateMedia::query()
            ->isolatedProduction()
            ->where('real_estate_profile_id', $profileModel->id)
            ->where('media_key', $media)
            ->first();

        if ($blob) {
            return $this->privateBytes((string) $blob->content_base64, $mime);
        }

        $path = trim((string) ($record['storage_path'] ?? ''));
        abort_unless(
            $path !== ''
            && str_starts_with($path, 'real-estate-private/'.$profileModel->id.'/')
            && Storage::disk('local')->exists($path),
            404
        );

        return $this->privateFile($path, $mime);
    }

    private function authorizeIsolatedOperator(): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)->exists(),
            403
        );
    }

    private function privateBytes(string $base64, string $mime): Response
    {
        $bytes = base64_decode($base64, true);
        abort_unless(is_string($bytes) && $bytes !== '', 404);

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function privateFile(string $path, string $mime): BinaryFileResponse
    {
        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
