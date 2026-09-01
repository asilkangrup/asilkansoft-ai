# Isolated Real Estate Match Ledger

The durable match ledger exists only for the fresh production identity:

- user_id = 40
- organization_id = 37
- ai_bot_id = 35
- Evolution instance = `emlak-ai-35`

## Purpose

`RealEstateMatchService` and the valuation/verification filters continuously recompute seller-investor compatibility. Profile JSON is intentionally mutable, so it is not sufficient for operational audit or production observability. `real_estate_match_events` records privacy-safe lifecycle transitions after the final verification/evidence filter has completed.

## Safety rules

The ledger never stores phone numbers, email addresses, WhatsApp numbers, document contents, audio, API keys or the seller's private minimum price. Free-text reasons and risks are redacted before persistence. The estimated transaction price is the safe match engine estimate; it is not a binding offer and is never represented as a verified completed-sale price.

An `active` event is accepted only when:

1. the source profile and both candidate conversations belong to user 40 / organization 37 / bot 35;
2. the final verification and evidence filter has run;
3. seller verification remains match-safe with risk below 55;
4. valuation freshness/comparable metadata exists;
5. at least two valuation comparables and two usable integrity comparables survive;
6. the match score is at least 55 and has a known grade.

When a previously active pair disappears from the final safe match set, the ledger appends a `removed` event. It never sends a WhatsApp message and never schedules a follow-up.

## Observability

`GET /api/real-estate/health` exposes `checks.match_ledger_ready` and `match_ledger_telemetry_24h`. Missing ledger schema/service blocks `ready_for_live_traffic`.

Operators can inspect the current privacy-safe state with:

```bash
php artisan real-estate:match-ledger --status=active
php artisan real-estate:match-ledger --profile=<profile-id> --status=all
```

The command displays internal profile IDs, score, grade and state only. It does not disclose contact data or private seller floors.
