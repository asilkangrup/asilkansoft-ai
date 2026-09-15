# Matbaa AI V1 – Professional acceptance criteria

This document is the release gate for the printing assistant. A change is not considered complete only because a single WhatsApp happy-path works.

## Conversation state
- Product, quantity, size, sides, substrate/paper and design mode must survive every turn and worker/container restart.
- The assistant must never ask for product again after the product is known unless the customer explicitly starts a new order.
- `@lid` and phone JIDs for the same WhatsApp contact must resolve to one session.
- Short replies are interpreted against the last pending field.
- `hazırla`, `devam`, `örnek`, `referans`, `böyle olsun`, `siz seçin`, `standart olsun` must not reset the order.

## Delegated choices
- “kağıt ve ölçüyü siz belirleyin” closes both paper and size in the same turn when a safe product default exists.
- A delegated choice must not be re-asked later.
- The system may choose a safe product default but may not invent price, turnaround or unsupported finishing details.

## Design modes
- `ready`: customer has finished artwork. Accept PDF/JPG/PNG, validate the role, then render a proof/mockup.
- `needs_design`: collect a brief and generate a draft.
- Images sent while `needs_design` are references by default unless the customer explicitly says they are finished print artwork.
- “örnek/referans/buna benzer” marks the latest asset as reference and must never switch the order to another product.
- A logo/reference image does not automatically mean artwork is print-ready.

## Design brief
Required semantic information:
- brand/company name,
- visual direction/style,
- at least one usable content/contact item (phone, e-mail, social, address, slogan, or free-form card content).

Natural shorthand such as `Soykan Auto, premium` must be accepted when brand/style are pending.
A standalone phone number must count as usable card content.

## Artwork safety
- Do not treat arbitrary square product/lifestyle photos as business-card artwork.
- Do not redraw customer-supplied ready artwork when producing a mockup.
- Two-page PDF: page 1 = front, page 2 = back for business-card proofing.
- Reference assets and ready artwork are distinct concepts in state.

## Response quality
- At most 1–2 questions per turn.
- No repeated full order summary on every turn.
- No repeated “not aldım/tamamdır” filler.
- Natural Turkish, concise and sales-oriented.
- Never fabricate price or delivery time.

## Release tests
At minimum regression tests must cover:
1. one-message order + delegated paper/size + needs-design,
2. short brand/style answer,
3. standalone phone answer,
4. reference image then `örnek` then `hazırla`,
5. ready JPG/PNG artwork,
6. two-page PDF artwork,
7. unrelated lifestyle/product image rejection,
8. state continuity across calls using the persistent cache store.
