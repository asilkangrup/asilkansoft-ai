# Isolated Emlak AI evidence-quality guard

Production scope is fixed to `user_id=40`, `organization_id=37`, `bot_id=35`, Evolution instance `emlak-ai-35`, and `/api/real-estate/whatsapp/webhook`.

This guard exists only inside that isolated runtime. It must not read from, reconnect to, or depend on legacy WAI accounts or WhatsApp instances.

## Why this guard exists

Field agreement alone is not enough to treat a seller as safely matchable. An advertisement screenshot can repeat the seller's own claims and therefore appear to "corroborate" city, district, square meters, parcel or title-deed information without providing meaningful independent evidence.

`RealEstateEvidenceQualityService` separates media findings into three provenance classes:

- `documentary`: title-deed, parcel/cadastre, zoning/municipality, e-Devlet or TAKBIS-like document images;
- `supporting`: listing screenshots, ordinary photos, maps and location images;
- `unknown`: media whose document type cannot be safely classified.

This classification is only an internal reliability signal. A photographed document is never treated as official legal verification.

## Matching rule

A seller can pass the evidence-quality gate only when at least one documentary finding:

1. has analysis confidence of at least 65;
2. exposes at least two relevant property fields; and
3. contains a useful identity signal, either:
   - both block/ada and parcel numbers, or
   - district + area plus a property/title-deed/zoning characteristic.

Listing screenshots and general property photos can support conversation context, but they cannot make a seller match-ready on their own.

`official_verification_complete` is always `false`; transaction-time official title-deed, zoning and legal checks remain separate responsibilities.

## Runtime integration

`RealEstateVerificationDecisionGuardService` persists `evidence_quality_intelligence`, adds `real_estate:evidence:*` CRM tags and forces `ready_for_match=false` when documentary support is insufficient.

`RealEstateMatchVerificationFilterService` independently re-checks seller evidence quality before allowing the seller to remain in another investor/buyer's opportunity list. This second check prevents stale or manually pre-populated match data from bypassing the decision guard.

For qualified sellers, `RealEstateOperatorAlertService` opens an `evidence_attention` alert when evidence is insufficient. The alert contains only safe status/count/reason metadata; it does not copy document contents, personal identifiers, phone numbers or API credentials.

No part of this feature enables follow-up messages or human takeover automatically.

## Tests

`tests/Feature/RealEstateEvidenceQualityTest.php` covers:

- listing screenshots being rejected as match-enabling evidence;
- clear title-deed identity signals supporting matching without claiming official verification;
- investor-side removal of sellers backed only by listing media;
- hard isolation from non-production users/bots.

The isolated CI workflow runs the complete `tests/Feature/RealEstate*.php` suite so evidence changes are checked together with readiness, webhook resilience, valuation freshness, matching, outbound delivery and operator-alert behavior.
