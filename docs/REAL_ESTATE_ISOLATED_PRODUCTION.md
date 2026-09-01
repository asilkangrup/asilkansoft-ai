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

The main message-memory pipeline now requires the exact user, organization and bot identity before any real-estate extraction, valuation, decision or matching work is executed. Conversation routing and profile extraction are also scoped to this fresh identity so a second bot belonging to the same WAI user cannot accidentally inherit the real-estate workflow.

## Follow-up policy

Automated follow-up messages are disabled for this bot. `follow_up_enabled` and `second_follow_up_enabled` must remain false. Real-estate decision, verification, valuation or opportunity-matching services must never schedule `next_follow_up_at` by themselves.

## Webhook security

The dedicated Evolution webhook requires the isolated instance name and a valid short-lived HS256 JWT signed with the encrypted organization webhook secret. Unsigned, invalid or foreign-instance webhook requests must be rejected.

## Dedicated OpenAI key security

The fresh real-estate bot stores its own `openai_api_key` encrypted at rest with Laravel `Crypt`/`APP_KEY`. The application decrypts it only through the bot model when creating the isolated OpenAI client. Other WAI bots retain their existing behavior and are not migrated by this real-estate-specific change.

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

The command is hard-scoped to `user_id=40` and `ai_bot_id=35`. It now audits valuation freshness first, then rebuilds seller decisions and verification guards, then rebuilds raw matches and applies valuation-freshness plus document-verification filters. Its output reports fresh/stale/missing valuation counts alongside verified and safely matched profile counts.

This prevents an investor-side rebuild from using either stale seller pricing or stale seller verification state.

## Production readiness gate

`GET /api/real-estate/health` is the single readiness gate. `ready_for_live_traffic` becomes `true` only when all of the following are true:

1. bot 35 belongs to user 40;
2. its configured Evolution instance is exactly `emlak-ai-35`;
3. webhook JWT authentication is configured;
4. the dedicated OpenAI key is configured and encrypted at rest;
5. WhatsApp is connected;
6. both automatic follow-up flags are disabled;
7. there are zero active follow-up records for bot 35.

The endpoint also returns `blocking_checks`, allowing operations to see exactly what still prevents live traffic without exposing credentials.

Before live customer traffic, also verify a signed webhook from `emlak-ai-35` is accepted while unsigned/foreign requests are rejected, ensure seller/investor smoke tests leave no synthetic data behind, verify an intentionally conflicting seller document is tagged `real_estate:verification:blocked` and produces no opportunity match, and verify a changed or expired seller valuation is tagged stale and removed from opportunity matching until refreshed.
