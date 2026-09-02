<?php

namespace App\Services;

use App\Models\ConversationControl;

class RealEstateGuardedMediaAnalysisService extends RealEstateMediaAnalysisService
{
    public function process(
        ConversationControl $conversation,
        string $instanceName,
        array $mediaContext
    ): ?array {
        $type = strtolower(trim((string) ($mediaContext['type'] ?? '')));

        // The WhatsApp parser always carries a media_context envelope, even for
        // ordinary text, location, video and audio messages. Those are not
        // image/document analysis candidates and must not pollute the media
        // rejection ledger or logs as unsupported MIME events.
        //
        // Actual image/document candidates still flow through the existing
        // strict preflight firewall so unsupported MIME, tenant drift, missing
        // envelopes and size violations remain fail-closed and observable.
        if (! in_array($type, ['image', 'document'], true)) {
            return null;
        }

        return parent::process(
            conversation: $conversation,
            instanceName: $instanceName,
            mediaContext: $mediaContext,
        );
    }
}
