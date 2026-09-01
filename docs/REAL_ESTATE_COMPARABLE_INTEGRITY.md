# Isolated real-estate comparable integrity guard

This guard applies only to the isolated production identity:

- user `40`
- organization `37`
- bot `35`
- Evolution instance `emlak-ai-35`

It does not fetch or reuse data from the legacy WAI account.

## Why this exists

A valuation can be recent and still be unsafe if its comparable set is internally weak. Freshness alone does not prove that the listings are relevant to the same location/property type, have usable price and area data, or form a statistically coherent asking-price set.

`RealEstateComparableIntegrityService` therefore runs as a second deterministic gate after valuation freshness.

## Checks

The service evaluates structured comparables for:

- valid HTTP/HTTPS source URL and host;
- positive listing price and area;
- location consistency with the seller city/district;
- property-type consistency;
- observations older than 90 days when an explicit observation date exists;
- unit-price spread and extreme outliers;
- internally consistent market / quick-sale / investor-buy ranges;
- market range that is not grossly detached from the comparable median TL/m².

The guard never treats listing prices as completed-sale prices. Its public payload always carries `price_basis=asking` and `official_sale_price_verified=false`.

## Decision behavior

A seller decision can use the valuation only when at least one usable comparable remains and no hard integrity failure exists. Investor matching is stricter: at least two usable comparables are required and a wide/outlier-heavy unit-price set is rejected.

`RealEstateValuationDecisionGuardService` stores a safe `valuation_integrity` summary in the structured profile and removes `ready_for_match` when integrity is insufficient. It uses the `repair_comparable_integrity` negotiation posture rather than allowing weak comparables to become a price anchor.

`RealEstateMatchValuationFreshnessFilterService` independently re-checks comparable integrity for every candidate seller. This prevents a stale or manually populated opportunity list from bypassing the guard.

## Safety boundaries

- No external credential is invented or used.
- No global WAI OpenAI key fallback is introduced.
- No follow-up message is scheduled.
- No customer message is sent by this guard.
- No official transaction price is inferred from an asking-price listing.
- Same-user profiles outside organization `37` are rejected.

## Tests

`RealEstateComparableIntegrityTest` covers:

- a coherent comparable set remaining decision/match safe;
- location mismatch blocking matching;
- extreme TL/m² spread blocking matching;
- inconsistent price ranges blocking decision use;
- match-filter enforcement;
- organization isolation.
