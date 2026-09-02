<?php

namespace App\Http\Controllers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class RealEstatePrivateMediaController extends Controller
{
    public function show(int $profile, string $media): Response
    {
        $user = auth()->user();

        abort_unless(
            $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()
                ->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)
                ->exists(),
            403
        );

        $profileModel = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey($profile)
            ->firstOrFail();
        $data = is_array($profileModel->data) ? $profileModel->data : [];
        $record = collect(is_array($data['manual_media'] ?? null) ? $data['manual_media'] : [])
            ->first(fn ($item): bool => is_array($item) && hash_equals(
                (string) ($item['id'] ?? ''),
                $media
            ));

        abort_unless(is_array($record), 404);

        $path = trim((string) ($record['storage_path'] ?? ''));
        abort_unless(
            $path !== ''
            && str_starts_with($path, 'real-estate-private/'.$profileModel->id.'/')
            && Storage::disk('local')->exists($path),
            404
        );

        return response()->file(
            Storage::disk('local')->path($path),
            [
                'Content-Type' => (string) ($record['mime_type'] ?? 'application/octet-stream'),
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
