# Isolated Emlak AI outbound delivery guard

Production scope is intentionally fixed to:

- user `40`
- organization `37`
- bot `35`
- Evolution instance `emlak-ai-35`
- webhook `/api/real-estate/whatsapp/webhook`

This guard exists only for that isolated real-estate runtime. It does not reconnect, reuse or depend on the legacy WAI user/bot/instance.

## Why it exists

Inbound webhook idempotency prevents the same Evolution event from being processed twice, but that alone cannot prevent this failure window:

1. AI generates a reply.
2. Evolution accepts the outbound WhatsApp message.
3. The application crashes before the local reply row or webhook receipt is finalized.
4. Evolution or the queue retries the inbound event.
5. Without an outbound ledger, the AI can send the same customer a second reply.

`real_estate_outbound_deliveries` closes that gap. One deterministic delivery key is created from the production instance and the inbound WhatsApp message id. A second attempt for the same inbound id reuses that delivery record and cannot perform another network send after a confirmed send.

## Conservative network-boundary policy

Immediately before calling Evolution the delivery is persisted as `sending`. If the HTTP call throws or a later worker finds an abandoned `sending` row, delivery becomes `uncertain` and is not automatically retried.

This is deliberate: once a request has crossed the network boundary, a timeout does not prove WhatsApp rejected it. Automatically retrying can create duplicate customer replies. An uncertain delivery is therefore quarantined for operator review and exposed as a production-readiness blocker.

## States

- `reserved`: safe to perform the first network send.
- `sending`: the network boundary has been entered.
- `sent`: Evolution confirmed a successful request.
- `uncertain`: delivery may or may not have reached WhatsApp; automatic retry is blocked.
- `abandoned`: an operator verified that no automatic resend should occur and closed the ambiguous record.

The ledger stores the first generated answer and its SHA-256 hash. If a process retry generates different wording for the same inbound message, the first reserved answer remains authoritative.

## Persistent conversation behavior

After a confirmed send, the assistant message is persisted idempotently to `chat_messages`. If Evolution returns no provider message id, a deterministic isolated synthetic id is used so the AI still remembers what it told the customer.

Trial usage is also tied to the delivery ledger through `trial_consumed_at`; a process retry cannot consume the same trial reply twice.

Inbound customer memory now uses the Evolution message id as an isolated persistence key. A failed/retried worker can continue safely without duplicating the customer's message or rerunning profile/valuation/matching extraction for the same stored message.

## Manual recovery without resend

An ambiguous network result must be checked in WhatsApp/Evolution before it is resolved. The recovery command never calls the WhatsApp send endpoint:

```bash
php artisan real-estate:resolve-outbound <delivery-id> sent --provider-id=<optional-provider-message-id>
php artisan real-estate:resolve-outbound <delivery-id> abandoned
```

Use `sent` only after externally confirming the customer already received the reply. It persists the assistant memory and consumes trial usage once, without sending anything again. Use `abandoned` when the ambiguous record should be closed without recording a delivered assistant message. Only exact user 40 / organization 37 / bot 35 / `emlak-ai-35` rows in `sending` or `uncertain` state can be resolved by this command.

## Readiness and telemetry

`GET /api/real-estate/health` exposes:

- `checks.outbound_delivery_guard_ready`
- `checks.unresolved_outbound_deliveries`
- `outbound_telemetry_24h.reserved`
- `outbound_telemetry_24h.sending`
- `outbound_telemetry_24h.sent`
- `outbound_telemetry_24h.uncertain`
- `outbound_telemetry_24h.network_retries`

`ready_for_live_traffic` is false when any `sending` or `uncertain` isolated delivery exists. This makes ambiguous customer delivery state visible instead of silently retrying it.

## Missing message ids

Production `messages.upsert` events without `data.key.id` are ignored with `missing_message_id`. Processing an event that cannot be durably identified would defeat both inbound and outbound idempotency guarantees.

## Tests

`RealEstateOutboundDeliveryGuardTest` verifies that:

- the same inbound id performs only one Evolution send;
- a different retry-generated answer is not sent;
- an uncertain network result is quarantined and not sent a second time;
- assistant persistence and trial accounting are idempotent;
- isolated inbound memory is deduplicated by WhatsApp message id;
- an inbound event without a message id is rejected before AI/network work;
- an uncertain delivery can be abandoned through the recovery command without any WhatsApp request.

The repository also contains `.github/workflows/isolated-real-estate-ci.yml`, which syntax-checks the isolated runtime and runs the focused real-estate feature tests on relevant pull requests and changes to `main`.
