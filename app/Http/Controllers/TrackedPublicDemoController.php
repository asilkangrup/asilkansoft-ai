<?php

namespace App\Http\Controllers;

use App\Services\WaiLeadDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackedPublicDemoController extends PublicDemoController
{
    public function chat(Request $request): JsonResponse
    {
        $response = parent::chat($request);

        $payload = $response->getData(true);

        if (
            $response->getStatusCode() < 400
            && is_array($payload)
            && ($payload['success'] ?? false) === true
        ) {
            $token = $this->tokenFromReferer((string) $request->headers->get('referer', ''));

            if ($token !== null) {
                app(WaiLeadDemoService::class)->markTestMessage($token);
            }
        }

        return $response;
    }

    private function tokenFromReferer(string $referer): ?string
    {
        if (! preg_match('~/demo/lead/([A-Za-z0-9]{48})(?:[/?#]|$)~', $referer, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }
}
