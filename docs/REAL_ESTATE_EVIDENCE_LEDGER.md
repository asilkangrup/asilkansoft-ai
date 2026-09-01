# Isolated Real-Estate Evidence Ledger

This ledger is production-scoped exclusively to `user_id=40`, `organization_id=37`, `bot_id=35` and the dedicated Emlak AI conversation pipeline.

## Purpose

`RealEstateProfile.data.media_findings` is intentionally bounded and mutable. The evidence ledger adds durable, idempotent lineage so operators can answer whether a profile was backed by documentary, supporting or unknown media without retaining another copy of the media or extracted document contents.

## Privacy boundary

`real_estate_evidence_events` stores only evidence metadata:

- a hash of the WhatsApp/Evolution message id, never the raw id;
- image/document source class and MIME type;
- a short sanitized document category;
- provenance class (`documentary`, `supporting`, `unknown`);
- confidence score;
- names of fields that were visible, never the extracted values;
- generic identity-signal names such as `block_parcel`;
- warning count, never warning text;
- a one-way fingerprint used for audit/deduplication.

It deliberately excludes raw media/base64, filenames, captions, summaries, phone numbers, email addresses, names, document text and extracted property values. It is not an official title-deed or government verification record.

## Idempotency and isolation

The event key is derived from the exact isolated scope, conversation id, WhatsApp message id and analysis version. Re-saving a profile or re-running observers cannot duplicate the same media event. Scope mismatch fails closed before any write.

The profile observer records evidence before downstream alert/match/case synchronization. No evidence-ledger operation sends WhatsApp messages, enables human takeover or schedules follow-ups.

## Readiness and telemetry

`GET /api/real-estate/health` exposes:

- `checks.evidence_ledger_ready`;
- `evidence_ledger_telemetry_24h.events`;
- documentary/supporting/unknown counts;
- high-confidence documentary count;
- unique profile count.

Missing schema/service is a production blocker and forces `ready_for_live_traffic=false`.
