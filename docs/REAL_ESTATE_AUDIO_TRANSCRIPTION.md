# Isolated Emlak AI voice transcription

Production scope is fixed to `user_id=40`, `organization_id=37`, `bot_id=35`, Evolution instance `emlak-ai-35`, and `/api/real-estate/whatsapp/webhook`.

## Runtime flow

1. Evolution delivers a `messages.upsert` payload to the dedicated authenticated real-estate webhook.
2. `RealEstateWhatsAppMessageParser` unwraps WhatsApp wrappers and classifies `audioMessage` as audio. Duration and file length are retained when Evolution supplies them.
3. `RealEstateAudioTranscriptionService` verifies bot 35 and instance `emlak-ai-35` before it downloads any media.
4. Audio is downloaded through `EvolutionMediaService`, decoded in memory and capped at 20 MiB before upload.
5. The decoded payload is written to a short-lived temporary file with a supported audio extension and sent to OpenAI transcription.
6. `RealEstateOpenAIClient::transcribeAudio()` obtains the key only from `AiBot::$openai_api_key`. There is no global `OPENAI_API_KEY` fallback.
7. A successful transcript replaces the placeholder before CRM/profile/value/decision processing, so structured property and investor memory see the actual spoken content in the same turn.
8. Transcript metadata is persisted on `chat_messages`: transcript, status, model, language, timestamp, duration and size.
9. Temporary audio is deleted in a `finally` block. Raw base64/audio and transcript text are not written to logs.

## Failure behavior

Unsupported formats, oversized voice notes, decode problems and transient transcription errors do not make the model pretend that it heard the audio. The conversation receives an explicit internal placeholder instructing the AI to ask for a written message. The failure status is stored for observability.

Supported MIME families include WhatsApp Opus/OGG, MP3/MPEG, MP4/M4A, AAC, WAV and WebM.

## Configuration

- `REAL_ESTATE_AUDIO_TRANSCRIPTION_MODEL` defaults to `gpt-4o-mini-transcribe`.
- `REAL_ESTATE_AUDIO_LANGUAGE` defaults to `tr`; set it to an empty value for model-side language detection.
- The production bot must have its own encrypted `openai_api_key`; the global WAI key must never be used.

## Health and telemetry

`/api/real-estate/health` exposes `checks.audio_transcription_ready` and 24-hour audio telemetry (`received`, `transcribed`, `failed`, `too_large`, `unsupported_format`). Readiness remains false until the transcription storage migration is present, in addition to the existing dedicated-key and WhatsApp connection requirements.

## User-only final actions

Voice transcription does not add any new manual step. The remaining user actions are still only:

1. Save the dedicated bot-level OpenAI key for bot 35 through the secure WAI UI.
2. Scan the QR code for `emlak-ai-35` from the phone that will own the WhatsApp session.
