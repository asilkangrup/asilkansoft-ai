# Isolated Real-Estate Outbound Safety Firewall

This guard exists only for the isolated production real-estate WhatsApp identity:

- `user_id=40`
- `organization_id=37`
- `bot_id=35`
- Evolution instance `emlak-ai-35`
- dedicated `/api/real-estate/whatsapp/webhook` pipeline

It is enforced inside `RealEstateOutboundDeliveryService` immediately before a reply is reserved or sent to WhatsApp. The guard is deterministic and does not call OpenAI or any shared WAI AI service.

## What is blocked

The v1 guard replaces the entire generated answer when it detects a positive, unsupported claim that could materially mislead a seller or investor, including:

- guaranteed/definite buyer, sale or offer claims;
- binding-offer wording that has not been established by a real transaction workflow;
- categorical official title-deed, zoning, encumbrance, lien, debt or registry verification claims;
- disclosure to an investor/buyer of a matched seller's confidential negotiation floor.

Negated disclaimers such as “hazır alıcı olduğunu söyleyemem” are intentionally allowed. A numeric value equal to a seller floor is not automatically secret merely because the number matches; the numeric leak detector also requires floor/minimum-price context.

## Replacement policy

Unsafe text is not partially edited. The full answer is replaced with a deterministic professional fallback. This avoids leaving misleading context around a removed phrase.

The safety firewall never:

- creates a follow-up;
- changes `human_takeover`;
- creates orders or finance leads;
- treats media/document analysis as official verification;
- exposes seller-private floor values;
- uses the global `OPENAI_API_KEY`.

## Privacy-safe audit ledger

Only replaced answers create `real_estate_outbound_safety_events` records. The ledger stores hashes and categorical reason codes, not the original or replacement message body.

Stored fields include exact isolated scope identifiers, conversation/profile IDs, SHA-256 hashes of the inbound message ID and generated answer, recipient role, reason codes, the safety-response version and timestamps.

The ledger does **not** store raw WhatsApp message IDs, phone numbers, customer names, email addresses, original AI answer text, media/document contents, seller floor values or API credentials.

If the audit table is temporarily unavailable during deployment, the deterministic replacement still runs; absence of the audit table must never turn an unsafe answer into an allowed answer.

## Delivery ordering

The enforced order is:

1. validate bot/instance scope;
2. resolve the exact 40/37/35 conversation from the isolated session;
3. run the outbound safety firewall;
4. reserve the already-safe answer in the durable outbound delivery ledger;
5. send the reserved answer at most once;
6. persist the confirmed assistant message.

Because the safe answer is stored before the network boundary, retries cannot replace it with a newly generated unsafe or different response for the same inbound WhatsApp message.
