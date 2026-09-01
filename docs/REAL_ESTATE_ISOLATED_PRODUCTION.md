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

Automated follow-up messages are disabled for this bot. `follow_up_enabled` and `second_follow_up_enabled` must remain false. Real-estate decision or opportunity-matching services must never schedule `next_follow_up_at` by themselves.

## Webhook security

The dedicated Evolution webhook requires the isolated instance name and a valid short-lived HS256 JWT signed with the encrypted organization webhook secret. Unsigned, invalid or foreign-instance webhook requests must be rejected.

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

Only matches scoring at least 55/100 are persisted. Contact information is not copied into match payloads. Stored match data uses internal profile/conversation IDs plus reasons, risks and an estimated transaction price.

The AI receives a privacy-safe summary of the strongest matches and is explicitly instructed not to claim a ready buyer, guaranteed sale or binding offer without real human verification.

### Rebuild command

Use the following command after bulk imports or material criteria changes:

```bash
php artisan wai:real-estate-rebuild-matches --limit=500
```

The command is hard-scoped to `user_id=40` and `ai_bot_id=35`.

## Production readiness checks

Before live customer traffic, verify:

1. `/api/real-estate/health` reports `isolated=true` and webhook authentication configured.
2. Bot 35 has a dedicated OpenAI API key configured.
3. Evolution instance `emlak-ai-35` is connected after the user scans the QR code.
4. Both follow-up flags remain disabled.
5. A signed webhook from `emlak-ai-35` is accepted while unsigned/foreign requests are rejected.
6. Seller/investor matching smoke tests leave no synthetic data behind.
