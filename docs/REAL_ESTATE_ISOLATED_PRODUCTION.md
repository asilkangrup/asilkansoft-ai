# Isolated Real Estate AI Production

This document defines the production invariants for the fresh real-estate WhatsApp AI installation.

## Hard isolation

Only the following production identity is allowed to use the real-estate pipeline:

- `user_id = 40`
- `organization_id = 37`
- `ai_bot_id = 35`
- Evolution instance: `emlak-ai-35`
- webhook: `/api/real-estate/whatsapp/webhook`

The real-estate services must not depend on the old WAI user/bot/instance. The bot must use its own encrypted bot-level `openai_api_key`; the real-estate AI path must not fall back to the global WAI OpenAI key.

`RealEstateIsolationService` is the central runtime boundary for this identity. The message-memory pipeline also requires the exact user, organization and bot identity before extraction, valuation, decision or matching work is executed. Conversation routing and profile extraction are scoped to the same fresh identity.

## Dedicated inbound WhatsApp runtime

The dedicated real-estate webhook no longer delegates customer traffic to the generic WAI controller. After JWT and tenant checks, `messages.upsert` enters `ProcessWhatsAppWebhook`; the job recognizes the exact isolated bot and immediately routes the payload to `RealEstateWhatsAppInboundService`, returning before the shared WAI commerce path can run.

For bot 35 this explicitly prevents:

- generic e-commerce order creation;
- finance-lead/group routing;
- generic WAI customer extraction/scoring side effects;
- automatic follow-up creation;
- accidental use of another tenant's conversation or message-status row.

The dedicated inbound service owns text/media extraction, 24-hour message-id deduplication, the `whatsapp:35:<number>` conversation key, real-estate memory processing, human-takeover handling, dedicated OpenAI reply generation, WhatsApp delivery, sent-message persistence and trial-message accounting.

`messages.update` is handled directly by the isolated service and updates delivery/read status only where `user_id=40`, `organization_id=37` and `ai_bot_id=35` all match. A WhatsApp message id collision in another WAI account therefore cannot mutate its message status.

The public webhook rejects payloads larger than 1 MiB before processing. Evolution media bytes are still fetched through the existing authenticated media-download path rather than being embedded in webhook JSON.

## Follow-up policy

Automated follow-up messages are disabled for this bot. `follow_up_enabled` and `second_follow_up_enabled` must remain false. Real-estate decision, verification, valuation or opportunity-matching services must never schedule `next_follow_up_at` by themselves.

This is now a runtime invariant as well as a setting: `ConversationFollowUp` forces every `user_id=40` / `ai_bot_id=35` record to `is_active=false` during save. Even an accidental shared-panel or legacy write cannot create a sendable follow-up record for Emlak AI.

## Webhook security

The dedicated Evolution webhook requires the isolated instance name and a valid short-lived HS256 JWT signed with the encrypted organization webhook secret. Unsigned, invalid or foreign-instance webhook requests must be rejected.

Only `messages.upsert` and `messages.update` are accepted as active events. Other signed events are acknowledged as ignored instead of entering the application pipeline.

## Dedicated OpenAI key security

The fresh real-estate bot stores its own `openai_api_key` encrypted at rest with Laravel `Crypt`/`APP_KEY`. The application decrypts it only through the bot model when creating the isolated OpenAI client. Other WAI bots retain their existing behavior and are not migrated by this real-estate-specific change.

`RealEstateOpenAIClient` additionally refuses any bot that is not the exact isolated real-estate identity before it reads or uses an API key. Profile extraction, media analysis and valuation therefore cannot accidentally use this client for another WAI tenant.

The public readiness endpoint never exposes the key. It reports only whether a dedicated key is configured and whether the raw stored value is decryptable as an encrypted value.

## Current comparable research and valuation freshness

A persisted valuation is not treated as permanently valid. `RealEstateValuationFreshnessService` protects pricing, negotiation and matching from stale market research.

Every new valuation is stamped server-side with:

- a SHA-256 fingerprint of material property facts;
- `researched_at`;
- a seven-day `expires_at` window;
- source count;
- structured comparable count;
- comparable quality;
- `usable_for_decision` and `usable_for_matching` flags.

The fingerprint covers material valuation inputs such as property type, city/district/neighborhood, m², block/parcel, title-deed type, zoning, shared-title status, asking price and location URL. If one of these facts changes, the old valuation becomes stale immediately even when its seven-day clock has not expired.

A valuation also becomes unusable when the research timestamp is missing/expired, no real source is recorded, structured comparables are missing, or confidence is below the safety threshold. Matching is stricter than conversational decision support: it requires at least two structured comparables and adequate confidence.

`RealEstateValuationService` reuses a still-fresh valuation for ordinary repeat questions, avoiding unnecessary API spend. Explicit requests such as “güncel”, “bugün”, “yeniden araştır” or “yeni emsal” force a new research pass. New research uses the dedicated bot-level OpenAI key only.

The research response persists structured comparables rather than only an opaque price conclusion. Each comparable can contain source, URL, listing/asking price, m², normalized TL/m², location, property type and an observed date when the source actually exposes one. Comparable URLs are deduplicated. The service also stores min/median/max asking-price-per-m² statistics when enough numeric data exists.

**Important pricing invariant:** listing/asking prices are not realized sale prices. The AI prompt, stored comparable payload and customer-facing context keep that distinction explicit. Unknown URLs, dates, prices or m² must never be invented.

`RealEstateValuationDecisionGuardService` runs after the normal decision engine. If a seller valuation is stale or insufficient it removes match readiness, changes negotiation posture to `refresh_valuation`, replaces the next-best action with a research-refresh instruction, and removes the `real_estate:state:ready_for_match` tag. It never schedules a follow-up.

`RealEstateMatchValuationFreshnessFilterService` runs after raw deterministic matching. A candidate seller is removed unless its valuation is currently safe for matching. Surviving match payloads include only non-sensitive valuation freshness metadata (status, quality, source/comparable counts and expiry), not customer contact information.

## Property verification and risk intelligence

`RealEstateVerificationService` performs a deterministic consistency check between structured seller information and facts extracted from incoming WhatsApp images/PDFs. This is deliberately separate from legal authentication.

The service compares the following fields when evidence is present:

- property type;
- city, district and neighborhood;
- m²;
- block / parcel;
- title-deed type;
- zoning status;
- visible asking price.

It stores a `verification_intelligence` block inside the isolated `real_estate_profiles.data` JSON with:

- verification status (`unverified`, `review`, `corroborated`, `high_risk`, `blocked`);
- deterministic risk score;
- corroborated fields;
- conflict fields and severity;
- missing verification fields;
- `safe_to_match` eligibility;
- a verification-specific next-best action.

Important: `legal_verification_complete` is intentionally always false from document/image analysis alone. A photograph or PDF can corroborate what is visible but does not prove current legal validity, ownership, encumbrances, zoning rights or official registry status.

Critical conflicts such as city, district, block or parcel mismatches block matching. High-risk conflicts such as significant m², property-type or title-deed mismatches also stop the system from presenting a seller as a ready opportunity until the inconsistency is resolved.

`RealEstateVerificationDecisionGuardService` forces `decision_intelligence.ready_for_match=false` when verification is unsafe and replaces the seller's next-best action with the verification action. The AI receives a privacy-safe verification summary and is instructed never to present visual-document analysis as official verification.

## Opportunity matching

`RealEstateMatchService` performs deterministic internal seller/investor matching. It is deliberately separate from OpenAI so raw matching remains available even before the dedicated OpenAI key is configured.

Matching considers:

- city and property-type compatibility as hard filters when both are known;
- district compatibility;
- investor maximum budget against the seller's investor/quick-sale/asking-price target;
- financing type;
- seller urgency;
- profile confidence;
- missing tapu and zoning information as risk signals.

Only matches scoring at least 55/100 are initially produced. Safety filtering is then applied in two independent layers:

1. `RealEstateMatchValuationFreshnessFilterService` removes sellers whose current pricing research is stale, source-less, under-supported by comparables or too low-confidence for matching.
2. `RealEstateMatchVerificationFilterService` removes sellers whose property/document verification is unsafe.

Contact information is not copied into match payloads. Stored match data uses internal profile/conversation IDs plus reasons, risks, estimated transaction price and non-sensitive freshness/verification metadata.

The AI receives a privacy-safe summary of the strongest safety-filtered matches and is explicitly instructed not to claim a ready buyer, guaranteed sale or binding offer without real human verification.

### Rebuild command

Use the following command after bulk imports, material criteria changes, new comparable research or newly analyzed documents:

```bash
php artisan wai:real-estate-rebuild-matches --limit=500
```

The command is hard-scoped to `user_id=40` and `ai_bot_id=35`. It audits valuation freshness first, then rebuilds seller decisions and verification guards, then rebuilds raw matches and applies valuation-freshness plus document-verification filters.

## Production readiness gate

`GET /api/real-estate/health` is the single readiness gate. It now audits both connection prerequisites and cross-product contamination. `ready_for_live_traffic` becomes `true` only when all of the following are true:

1. bot 35 belongs to user 40, is a real-estate bot, and its instance is exactly `emlak-ai-35`;
2. organization 37 belongs to user 40 and is active;
3. webhook JWT authentication is configured;
4. the dedicated OpenAI key is configured and encrypted at rest;
5. AI/subscription state allows replies;
6. WhatsApp is connected/open;
7. finance group routing is disabled;
8. both automatic follow-up flags are disabled;
9. there are zero active follow-up records for bot 35;
10. there are zero generic e-commerce order records for bot 35;
11. there are zero finance-lead records for bot 35.

The endpoint also reports `dedicated_inbound_pipeline=true`, `shared_wai_commerce_pipeline=false`, `follow_up_runtime_blocked=true` and a `blocking_checks` list without exposing credentials.

Before live customer traffic, verify a signed webhook from `emlak-ai-35` is accepted while unsigned/foreign requests are rejected; verify `messages.update` cannot modify another tenant's message; ensure seller/investor smoke tests leave no synthetic data; verify an intentionally conflicting seller document is blocked from matching; and verify a changed or expired valuation is removed from opportunity matching until refreshed.
