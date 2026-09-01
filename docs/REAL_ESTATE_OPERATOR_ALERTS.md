# Isolated Emlak AI operator alert queue

Production scope is fixed to `user_id=40`, `organization_id=37`, `bot_id=35`, Evolution instance `emlak-ai-35`, and `/api/real-estate/whatsapp/webhook`.

The operator alert queue is an internal CRM/operations safety layer. It does **not** send WhatsApp messages, schedule follow-ups, or enable human takeover automatically.

## Alert types

- `verification_risk`: opens when document/media verification is `blocked` or `high_risk`. Critical location/parcel conflicts become `critical` alerts.
- `hot_lead`: opens for isolated seller/investor conversations at 70+ lead score with `hot` temperature while the lead is still active.
- `valuation_attention`: opens for qualified sellers whose persisted valuation is not safe for investor matching because it is missing, stale, expired, low-confidence, or lacks sufficient comparables.

Alerts are deduplicated per profile and condition. Re-saving the same profile does not create duplicate queue items. When the triggering condition disappears, the managed alert is automatically resolved.

No phone number, customer name, email, document body, raw image, audio, base64 content, or OpenAI key is copied into alert payloads. Payloads contain only operational state such as lead score, verification status, conflict field names, valuation freshness reasons and comparable counts.

## Safe operator inspection

```bash
php artisan real-estate:operator-alerts
php artisan real-estate:operator-alerts --severity=critical
php artisan real-estate:operator-alerts --json
```

A queue item can be marked resolved without sending anything to the customer:

```bash
php artisan real-estate:operator-alerts --resolve=<id>
```

This command is hard-scoped to the isolated production account. It cannot list or resolve alerts belonging to another WAI tenant.

## Runtime isolation

`RealEstateProfileObserver` first verifies the exact conversation identity through `RealEstateIsolationService::supportsConversation()`. Therefore profile changes outside `user_id=40 / organization_id=37 / bot_id=35` never enter the alert engine.

Follow-up messages remain disabled. The queue is for operator visibility and intervention planning only.
