<?php

namespace App\Http\Controllers;

use App\Models\WaiDemoLead;
use App\Services\WaiLeadDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            $token = $this->tokenFromReferer((string) $request->headers->get('referer', ''))
                ?? $this->tokenFromPayload($request);

            if ($token !== null) {
                app(WaiLeadDemoService::class)->recordTestConversation(
                    token: $token,
                    userMessage: (string) $request->input('message', ''),
                    assistantMessage: (string) ($payload['message'] ?? ''),
                );
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

    private function tokenFromPayload(Request $request): ?string
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return null;
        }

        $company = trim((string) $request->input('company_name', ''));
        $description = trim((string) $request->input('company_description', ''));

        if ($company === '' || $description === '') {
            return null;
        }

        return WaiDemoLead::query()
            ->where('company_name', $company)
            ->where('company_description', $description)
            ->latest('id')
            ->value('token');
    }
}
