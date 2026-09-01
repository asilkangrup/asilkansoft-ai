# Real Estate WhatsApp AI Rollout

## Scope

The primary account (`user_id=1`) uses the dedicated real-estate WhatsApp AI. Other SaaS users continue to use the standard WAI OpenAI service.

## Current pipeline

1. WhatsApp webhook receives a customer message.
2. `MemoryService` stores the message.
3. `RealEstateConversationService` classifies seller / investor / general intent.
4. `RealEstateProfileService` extracts explicit property or investor facts into `real_estate_profiles`.
5. If the customer asks for a valuation and minimum location data exists, `RealEstateValuationService` performs a current-market research pass and persists the structured valuation.
6. Persistent profile + valuation context is injected into the next OpenAI reply so already-known facts are not repeatedly requested.
7. `RealEstateOpenAIService` produces the customer-facing professional WhatsApp reply.

## Production requirements

Run migrations after deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Optional model overrides:

```dotenv
REAL_ESTATE_OPENAI_MODEL=gpt-5.4
REAL_ESTATE_EXTRACTOR_MODEL=gpt-5-mini
REAL_ESTATE_VALUATION_MODEL=gpt-5.4
```

If the model names available to the configured OpenAI project differ, set these values to supported model IDs before live testing.

## Release verification

Check:

```text
GET /api/health/release
```

Expected release marker:

```json
{"ok":true,"release":"real-estate-whatsapp-v1"}
```

Then verify the WhatsApp instance is connected and its webhook points to:

```text
/api/whatsapp/webhook
```

## Smoke-test scenarios

### Seller

`Marmaris Hisarönü'nde 4.800 m2 tarlam var. 4,5 milyon istiyorum ve biraz acil satmam lazım.`

Expected behavior:
- seller intent retained,
- property/location/area/asking price/urgency persisted,
- bot does not ask for already supplied facts,
- bot asks only the next 1-2 useful missing facts.

### Valuation

`Sence kaç para eder, yatırımcı kaça alır?`

Expected behavior:
- valuation runs only when minimum location/property data exists,
- market / quick-sale / investor-buy ranges remain conservative,
- confidence and missing data are considered,
- no guaranteed sale/investment language.

### Investor

`Muğla tarafında 4 milyon nakit bütçeyle yatırım için tarla arıyorum.`

Expected behavior:
- investor intent retained,
- budget/location/property type/financing extracted,
- next question focuses on investment goal or preferred district rather than repeating budget.

## Known remaining production work

- Real media bytes (photo/PDF/document) still need to be passed to a multimodal analysis pipeline before the AI can truthfully read tapu, parcel screenshots, listing screenshots or documents.
- Live deployment and WhatsApp QR connection must be verified on the server.
- Add richer panel UI for structured real-estate profiles and valuation cards.
