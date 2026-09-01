# Isolated Real Estate Case Lifecycle

This ledger is scoped exclusively to the fresh production real-estate account:

- user_id: `40`
- organization_id: `37`
- ai_bot_id: `35`
- Evolution instance: `emlak-ai-35`

It does not read from, write to, reconnect, or depend on the old WAI account or the old WhatsApp instance.

## Purpose

`real_estate_case_events` is an append-only, privacy-minimized CRM lifecycle ledger. It records meaningful changes in the operational state of a seller/investor case so operators can understand how a case progressed without reconstructing history from mutable profile JSON.

Recorded state is intentionally limited to workflow metadata:

- profile type,
- qualification stage,
- lead score and temperature,
- valuation readiness,
- matching readiness,
- whether a valuation exists,
- number/grade of safe candidate matches.

The ledger never stores customer message text, phone/e-mail, document or audio contents, API keys, or the seller's private minimum-price floor.

## Event types

- `case_opened`
- `match_ready`
- `match_blocked`
- `valuation_ready`
- `stage_changed`
- `temperature_changed`
- `match_candidates_changed`
- `state_changed`

Identical repeated saves do not create duplicate events. If a case later returns to a previous state after an intervening transition, that transition can be recorded again because event idempotency is anchored to the immediately preceding lifecycle event.

## Runtime safety

The service requires `RealEstateIsolationService::supportsConversation()` before writing an event. It performs no outbound WhatsApp calls, never enables human takeover, and never schedules a follow-up. Follow-up settings remain disabled for bot 35.

## Operator inspection

Use the privacy-safe command:

```bash
php artisan real-estate:case-timeline
php artisan real-estate:case-timeline 123 --limit=50
```

The command deliberately omits customer contact details and free-text CRM fields.
