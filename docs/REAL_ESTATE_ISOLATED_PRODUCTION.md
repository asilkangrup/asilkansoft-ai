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

## Follow-up policy

Automated follow-up messages are disabled for this bot. `follow_up_enabled` and `second_follow_up_enabled` must remain false. Real-estate decision, verification or opportunity-matching services must never schedule `next_follow_up_at` by themselves.

## Webhook security

The dedicated Evolution webhook requires the isolated instance name and a valid short-lived HS256 JWT signed with the encrypted organization webhook secret. Unsigned, invalid or foreign-instance webhook requests must be rejected.

## Dedicated OpenAI key security

The fresh real-estate bot stores its own `openai_api_key` encrypted at rest with Laravel `Crypt`/`APP_KEY`. The application decrypts it only through the bot model when creating the isolated OpenAI client. Other WAI bots retain their existing behavior and are not migrated by this real-estate-specific change.

The public readiness endpoint never exposes the key. It reports only whether a dedicated key is configured and whether the raw stored value is decryptable as an encrypted value.

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

`RealEstateMatchService` performs deterministic internal seller/investor matching. It is deliberately separate from OpenAI so matching remains available even before the dedicated OpenAI key is configured.

Matching considers:

- city and property-type compatibility as hard filters when both are known;
- district compatibility;
- investor maximum budget against the seller's investor/quick-sale/asking-price target;
- financing type;
- seller urgency;
- profile confidence;
- missing tapu and zoning information as risk signals.

Only matches scoring at least 55/100 are initially produced. `RealEstateMatchVerificationFilterService` then removes any match whose seller is not verification-safe. Contact information is not copied into match payloads. Stored match data uses internal profile/conversation IDs plus reasons, risks and an estimated transaction price.

The AI receives a privacy-safe summary of the strongest verification-safe matches and is explicitly instructed not to claim a ready buyer, guaranteed sale or binding offer without real human verification.

### Rebuild command

Use the following command after bulk imports, material criteria changes or newly analyzed documents:

```bash
php artisan wai:real-estate-rebuild-matches --limit=500
```

The command is hard-scoped to `user_id=40` and `ai_bot_id=35`. It now uses two passes: first every seller in scope is re-verified and decision-guarded, then opportunity matches are rebuilt and verification-filtered. This prevents investor-side rebuilds from using stale seller verification state.

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

Before live customer traffic, also verify a signed webhook from `emlak-ai-35` is accepted while unsigned/foreign requests are rejected, ensure seller/investor smoke tests leave no synthetic data behind, and verify that an intentionally conflicting seller document is tagged `real_estate:verification:blocked` and produces no opportunity match.
