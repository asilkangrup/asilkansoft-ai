<?php

namespace App\Services;

class RealEstateWhatsAppMessageParser
{
    public function extract(
        array $payload,
        string $instance,
        string $messageId,
    ): array {
        $messagePayload = data_get($payload, 'data.message', []);

        if (! is_array($messagePayload)) {
            return ['', []];
        }

        $messagePayload = $this->unwrap($messagePayload);

        $context = [
            'type' => 'text',
            'url' => null,
            'mime_type' => null,
            'filename' => null,
            'caption' => null,
            'duration' => null,
            'size' => null,
            'transcript' => null,
            'transcription_status' => null,
            'transcription_model' => null,
            'transcription_language' => null,
            'transcribed_at' => null,
            'message_id' => $messageId !== '' ? $messageId : null,
            'instance_name' => $instance,
            'message_envelope' => data_get($payload, 'data', []),
        ];

        $message = data_get($messagePayload, 'conversation')
            ?? data_get($messagePayload, 'extendedTextMessage.text');

        if (is_string($message) && trim($message) !== '') {
            return [trim($message), $context];
        }

        $image = data_get($messagePayload, 'imageMessage');

        if (is_array($image)) {
            $context['type'] = 'image';
            $context['url'] = data_get($image, 'url');
            $context['mime_type'] = data_get($image, 'mimetype');
            $context['filename'] = data_get($image, 'fileName') ?? 'Fotoğraf';
            $context['caption'] = data_get($image, 'caption');
            $context['size'] = $this->numericMeta(data_get($image, 'fileLength'));

            return [
                trim((string) ($context['caption'] ?: '[Fotoğraf]')),
                $context,
            ];
        }

        $document = data_get($messagePayload, 'documentMessage');

        if (is_array($document)) {
            $context['type'] = 'document';
            $context['url'] = data_get($document, 'url');
            $context['mime_type'] = data_get($document, 'mimetype');
            $context['filename'] = data_get($document, 'fileName') ?? 'Belge';
            $context['caption'] = data_get($document, 'caption');
            $context['size'] = $this->numericMeta(data_get($document, 'fileLength'));

            return [
                trim((string) ($context['caption'] ?: '[Belge]')),
                $context,
            ];
        }

        $video = data_get($messagePayload, 'videoMessage');

        if (is_array($video)) {
            $context['type'] = 'video';
            $context['url'] = data_get($video, 'url');
            $context['mime_type'] = data_get($video, 'mimetype');
            $context['filename'] = data_get($video, 'fileName') ?? 'Video';
            $context['caption'] = data_get($video, 'caption');
            $context['duration'] = $this->numericMeta(data_get($video, 'seconds'));
            $context['size'] = $this->numericMeta(data_get($video, 'fileLength'));

            return [
                trim((string) ($context['caption'] ?: '[Video]')),
                $context,
            ];
        }

        $audio = data_get($messagePayload, 'audioMessage');

        if (is_array($audio)) {
            $context['type'] = 'audio';
            $context['url'] = data_get($audio, 'url');
            $context['mime_type'] = data_get($audio, 'mimetype');
            $context['filename'] = 'Sesli mesaj';
            $context['duration'] = $this->numericMeta(data_get($audio, 'seconds'));
            $context['size'] = $this->numericMeta(data_get($audio, 'fileLength'));
            $context['transcription_status'] = 'pending';

            // This text is replaced by the dedicated transcription pipeline
            // before CRM/profile processing whenever transcription succeeds.
            return ['[Sesli mesaj - içerik henüz metne çevrilmedi]', $context];
        }

        $location = data_get($messagePayload, 'locationMessage')
            ?? data_get($messagePayload, 'liveLocationMessage');

        if (is_array($location)) {
            return $this->location($location, $context);
        }

        $buttonText = data_get($messagePayload, 'buttonsResponseMessage.selectedDisplayText')
            ?? data_get($messagePayload, 'templateButtonReplyMessage.selectedDisplayText');

        if (is_string($buttonText) && trim($buttonText) !== '') {
            return [trim($buttonText), $context];
        }

        $listText = data_get($messagePayload, 'listResponseMessage.singleSelectReply.selectedRowId')
            ?? data_get($messagePayload, 'listResponseMessage.title');

        if (is_string($listText) && trim($listText) !== '') {
            return [trim($listText), $context];
        }

        return ['', $context];
    }

    private function unwrap(array $messagePayload): array
    {
        $paths = [
            'ephemeralMessage.message',
            'viewOnceMessage.message',
            'viewOnceMessageV2.message',
            'viewOnceMessageV2Extension.message',
            'documentWithCaptionMessage.message',
        ];

        for ($depth = 0; $depth < 8; $depth++) {
            $nested = null;

            foreach ($paths as $path) {
                $candidate = data_get($messagePayload, $path);

                if (is_array($candidate) && $candidate !== []) {
                    $nested = $candidate;
                    break;
                }
            }

            if ($nested === null) {
                break;
            }

            $messagePayload = $nested;
        }

        return $messagePayload;
    }

    private function location(array $location, array $context): array
    {
        $latitude = data_get($location, 'degreesLatitude');
        $longitude = data_get($location, 'degreesLongitude');
        $name = trim((string) (data_get($location, 'name') ?? ''));
        $address = trim((string) (data_get($location, 'address') ?? ''));
        $providedUrl = trim((string) (data_get($location, 'url') ?? ''));

        $hasCoordinates = is_numeric($latitude) && is_numeric($longitude);
        $mapUrl = $providedUrl;

        if ($mapUrl === '' && $hasCoordinates) {
            $mapUrl = 'https://www.google.com/maps?q='.
                rawurlencode((string) $latitude.','.(string) $longitude);
        }

        $parts = ['Konum paylaşıldı.'];

        if ($name !== '') {
            $parts[] = 'Yer: '.$name.'.';
        }

        if ($address !== '') {
            $parts[] = 'Adres: '.$address.'.';
        }

        if ($hasCoordinates) {
            $parts[] = 'Koordinat: '.(string) $latitude.', '.(string) $longitude.'.';
        }

        if ($mapUrl !== '') {
            $parts[] = 'Harita: '.$mapUrl;
        }

        $context['type'] = 'location';
        $context['url'] = $mapUrl !== '' ? $mapUrl : null;
        $context['filename'] = 'WhatsApp konumu';
        $context['caption'] = trim(implode(' ', array_filter([$name, $address])));

        return [trim(implode(' ', $parts)), $context];
    }

    private function numericMeta(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 0 ? $value : null;
    }
}
