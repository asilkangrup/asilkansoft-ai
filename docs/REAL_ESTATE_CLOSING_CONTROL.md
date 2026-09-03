# Isolated Real-Estate Closing Control

This control surface belongs only to the production identity:

- `user_id=40`
- `organization_id=37`
- `bot_id=35`
- Evolution instance `emlak-ai-35`
- WhatsApp ingress remains `/api/real-estate/whatsapp/webhook`

It never uses or falls back to the legacy WAI user/bot/instance.

## Purpose

The closing workspace is a human-operated CRM control for a seller/investor pair after a real offer has been explicitly accepted. It does not send WhatsApp messages, schedule follow-ups, infer that money was received, or claim that title transfer was completed.

`RealEstateClosingRiskService` derives privacy-safe operational signals from the isolated seller authorization record and the closing case stored in the seller profile. Examples include:

- expired or incomplete seller authorization;
- accepted deal without an opened closing case;
- missing title/identity/encumbrance/tax/payment-plan checks;
- missing or past/unconfirmed deed appointment;
- deposit amount inconsistencies;
- deed transfer marked complete without verified final payment;
- final payment verified while deed transfer still needs confirmation;
- a non-completed closing case not updated for more than 48 hours.

Every assessment carries `human_confirmation_required=true`, `automatic_outbound_allowed=false`, and `customer_follow_up_allowed=false`.

## Operator next-best action

The risk engine chooses a deterministic human action with critical conditions taking priority over normal warnings. It never executes the action itself. The Filament closing workspace shows the current risk and the recommended operator action above the checklist.

## Observability

Run:

```bash
php artisan real-estate:closing-health --json
```

The command returns aggregate counts only. It does not expose customer names, phone numbers, operator notes, property payloads, or the seller's private floor. A critical transaction workload is reported as `human_attention_required` but is intentionally not a WhatsApp live-traffic blocker.

`RealEstateLiveReadinessService` embeds the same aggregate snapshot under `closing_health`. The observability mechanism itself must be available, but an individual transaction waiting for a human closing action does not disable the WhatsApp bot.

## Isolation and safety invariants

- Queries originate from `RealEstateClosingService::acceptedDeals()` and isolated production profiles.
- Foreign user/org/bot records are ignored.
- Closing state is stored under `transaction_closing_cases` in the isolated seller profile; no migration is required.
- No OpenAI call is made by closing risk evaluation.
- No outbound WhatsApp call is made by closing risk evaluation.
- No follow-up date is created or changed by closing risk evaluation.
- Private seller floor and customer PII are excluded from health telemetry.
