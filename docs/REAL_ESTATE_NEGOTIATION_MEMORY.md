# Isolated Emlak AI negotiation memory

Production scope is fixed to:

- user `40`
- organization `37`
- bot `35`
- Evolution instance `emlak-ai-35`
- `/api/real-estate/whatsapp/webhook`

This feature is a durable CRM memory for changing customer price positions and transaction preferences. It does **not** create offers, acceptances, counter-offers, contracts, follow-ups, or human takeover actions.

## What is remembered

For seller profiles:

- asking-price changes;
- seller minimum-price changes;
- urgency changes.

For investor/buyer profiles:

- minimum/maximum budget changes;
- financing preference;
- transaction timeline;
- risk preference.

Every event keeps the current and previous structured value, direction of change, source customer-message id, timestamp, and a material-change flag. The raw WhatsApp message is not copied into the negotiation ledger or its AI summary.

## Provenance rule

A negotiation event may be created only when all of the following are true:

1. the profile belongs to the isolated production identity;
2. the conversation also matches the isolated production identity;
3. a real customer-authored chat message exists for that conversation;
4. that customer message is recent enough to be the source of the profile update;
5. the structured value differs from the last stored position.

Background recalculations, operator-side profile saves, assistant messages, verification updates, valuation refreshes, and match rebuilds cannot manufacture a new customer price position without a recent customer source message.

## Confidential seller floor

`seller_minimum_price` is marked confidential. It is useful inside the seller-side CRM context, but must never be automatically exposed to an investor/buyer. The dedicated AI prompt explicitly prohibits disclosure of the private seller floor and prohibits inventing an acceptance or counter-offer.

The operational CLI also hides the floor amount:

```bash
php artisan real-estate:negotiation-timeline
php artisan real-estate:negotiation-timeline --profile=123 --limit=25
```

The command shows only scoped Emlak AI events; seller floor values render as `[CONFIDENTIAL FLOOR UPDATED]`.

## Material movement

A numeric position change of at least 10% is tagged as a material change. Summary data includes trajectory percentages for seller asking price, seller floor, and investor maximum budget. These are decision-support signals only; price movement must never be used to pressure a customer or fabricate urgency.

## AI context

The negotiation summary is stored under `negotiation_memory_intelligence` in the isolated real-estate profile and is also injected through `RealEstateNegotiationMemoryService::promptFor()` into the internal real-estate context.

Guardrails supplied to the model include:

- positions are not binding offers;
- seller minimum price is confidential;
- do not disclose a private seller floor to buyers/investors;
- do not invent acceptance/rejection/counter-offer events;
- do not exploit urgency or price reductions for pressure tactics.

## Observability and readiness

`RealEstateReadinessService` checks for the ledger table and negotiation-memory service. The health snapshot exposes only aggregate 24-hour counts such as total events and price/budget changes. It never returns raw messages, phone numbers, API keys, or private floor amounts.

## Follow-up safety

This feature does not write `next_follow_up_at`, does not enable either bot follow-up flag, and does not send WhatsApp messages. Existing isolated follow-up runtime blocking remains unchanged.

## Tests

`RealEstateNegotiationMemoryTest` covers:

- durable seller asking/floor trajectory;
- confidentiality of the seller floor;
- investor budget/term trajectory;
- idempotence under background profile recalculation;
- no raw phone/message leakage in AI memory;
- hard isolation from non-production users/bots;
- follow-up and human-takeover invariants.
