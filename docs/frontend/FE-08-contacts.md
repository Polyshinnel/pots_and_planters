# FE-08 — Contacts page (Pots & Planters 2.0)

## Context and objective

Build a complete, responsive `/contact` or `/contacts` page (use the route and naming conventions already present in the repository) for a UAE-based, B2B-first pots, planters and artificial greenery business. The page should make it easy for architects, interior and landscape designers, contractors, hotels, property developers and other buyers to contact the team, visit a showroom and discuss a project.

This is **frontend/mock-data work only**. FE-06 already implemented `ContactEnquiryForm`; reuse it. FE-05 checkout and FE-07 catalogues are finished. Preserve all manually edited layout, design and copy on existing pages.

## Before changing code

1. Inspect frontend routing, layout, header/footer links, existing contact page or stub, contact/social configuration, current `ContactEnquiryForm`, shared UI components and responsive patterns.
2. Find the actual contact details and showroom data already available in the project, including old-site references if present. Do not invent company facts.
3. Follow the existing frontend framework, component conventions, icon library and design tokens. Do not install new packages without a clear need.
4. Reuse existing WhatsApp link builders, form modal behavior and mock-data conventions.

## Information architecture and content

### A. Page introduction

- One H1: `Contact Us`.
- Short, confident B2B-focused introduction, e.g. `Planning a hospitality, commercial or residential project? Get in touch to discuss planters, artificial greenery, product options and project requirements.`
- Avoid unverified promises about response times, stock, installation, delivery, trade discounts or services.
- Optional restrained action links to `Send an Enquiry` (scroll to form) and WhatsApp, only if verified/configured.

### B. Direct contact methods

Present clearly differentiated, accessible methods using the **real existing** configuration:

- Phone: `tel:` link.
- Email: `mailto:` link.
- WhatsApp: use existing shared URL/link helper, with an optional generic project enquiry prefill.
- Additional established company social accounts only if configured and useful.

Show only fields for which actual values exist. Never insert made-up placeholders into the user-facing page. Labels should explain what clicking will do.

### C. Showrooms / locations

- Support **zero, one or multiple** showrooms via a typed mock data array or existing shared configuration; structure should be ready to move into the Laravel API in Phase 3.
- For each location, support: stable id, display name, full address, optional area/city, optional phone, optional email, optional opening hours, optional verified map/directions URL and optional coordinates.
- Display cards with showroom name, readable address, and `Get Directions` only if a real maps link or valid location information is available. Use `target="_blank"` with safe rel attributes for external maps.
- If multiple locations exist, render a responsive card list/grid. Do not hardcode a single showroom in the component.
- If there is no verified showroom data, omit the showroom section or show a neutral non-factual contact prompt. Do not render empty cards or fabricated locations.
- An embedded map is **optional**, not a requirement. If using one, ensure location accuracy, privacy/performance awareness, lazy loading and an accessible text address; a static card with directions link is sufficient and often preferable.
- No Google Maps API integration or new API keys in this phase.

### D. Contact form

- Integrate the **existing FE-06 `ContactEnquiryForm`**, not a duplicate form.
- Heading: `Tell Us About Your Project` or similarly concise B2B language.
- Supporting text should invite requests about products, specifications, quantities and suitable solutions.
- Preserve the form's existing required/optional fields, validation, accessible errors, typed payload and mock mode.
- Do not claim that an enquiry has been sent, saved or delivered. Do not clear fields based on a fake success response.
- If a page-specific context/source is supported by the existing payload, pass a stable source such as `contacts_page`; otherwise avoid breaking the established contract merely to add tracking.
- On mobile, inputs and submit/preview controls must be full-width or otherwise comfortable to use.

### E. Closing B2B CTA

- Add a compact, understated callout for project support, for example: `Need help choosing planters for your space? Tell us about your project and we'll help you explore suitable options.`
- Link to the same on-page form or verified WhatsApp contact; do not create another form.
- Do not add unsupported claims such as free site visits, guaranteed availability or installation services.

## Suggested desktop/mobile layout

**Desktop**: introduction; direct-contact methods; showroom cards if verified; two-column or balanced contact/form section where appropriate; compact closing CTA. Keep the form visually prominent and easy to locate.

**Mobile**: introduction; tap-friendly phone/email/WhatsApp links; location cards with directions; contact form; closing CTA. Avoid oversized hero sections, embedded map dominating the viewport, horizontal overflow or excessively tall blank spaces.

Use the established restrained Pots & Planters visual language, existing olive accent (`#989B86` if still current in tokens), consistent spacing and type hierarchy. Do not redesign global header/footer.

## Data and architecture

- Prefer existing shared contact/showroom configuration if present. If not, create a small typed mock-data module following repository conventions, with explicit optional fields.
- Do not duplicate contact values in JSX, cards, footer and WhatsApp links. Keep one authoritative frontend source where feasible.
- A location may have no map URL or hours; the UI must gracefully hide missing details.
- Prepare data shapes for future Laravel/Filament management without implementing backend endpoints now.
- Keep content indexable: server-render static page copy and address details where supported by the app architecture.
- Set meaningful metadata (title, description) using the existing SEO approach; do not fabricate structured business address data.
- Ensure correct route and header/footer `Contact` navigation links, without breaking existing links.

## Explicit exclusions

- No Laravel API, Telegram integration, backend submissions or DB writes.
- No new form implementation, CRM, accounts, scheduling, booking or map-provider integration.
- No invented phone numbers, email addresses, physical addresses, opening hours, testimonials or guarantees.
- No refactor of FE-05 cart, FE-06 forms, FE-07 catalogues or unrelated pages.
- No new dependency unless strictly justified.

## Acceptance criteria

1. Contacts route loads and is reachable from existing navigation/footer.
2. Page communicates the B2B purpose clearly without feeling like a generic retail contact page.
3. Phone, email and WhatsApp links work and use existing real values/configuration.
4. Showroom cards handle 0/1/multiple locations, optional fields and external directions links safely.
5. No invented contact details are visible.
6. The existing `ContactEnquiryForm` is reused and its validation remains intact.
7. Mock mode does not fake submission success or clear data as though an API accepted it.
8. Layout works on desktop, tablet and narrow mobile (including ~375px and ~320px).
9. Keyboard navigation, labels, focus indicators and links are accessible.
10. Existing homepage, product, checkout, catalogue pages and their forms remain unchanged and functional.
11. TypeScript checks and production build pass (or pre-existing unrelated failures are reported explicitly).
12. Final report lists changed files, data sources used, missing real-world content needing confirmation and checks performed.

## Execution instructions for Codex

Inspect existing code and sources first. Implement the page in small steps: route and data, contact methods, showrooms, existing form integration, responsive styling, metadata and regression checks. Preserve existing visual conventions and all user manual edits. If the repository lacks verified showroom or contact information, do not guess; make the layout resilient and report the missing information for later completion.

---

## Confirmed showroom and contact data — user-supplied amendment (mandatory)

The following information is **explicitly provided by the project owner** and takes precedence over the earlier instruction to discover missing showroom data. Populate the typed frontend data source with **all three locations**, preserving the business names, exact contact details and distinct countries. These are user-provided facts, not independently verified geocodes or opening hours.

### Location 1 — DEVA HARMONY TRADING

- **Display name:** `DEVA HARMONY TRADING`
- **Address:** `Warehouse#4, Al Kahraman Street#4, Al Quoz Industrial 4, Dubai`
- **City/country:** Dubai, UAE
- **Phone 1:** `+971 58 580 7443` (`tel:+971585807443`)
- **Phone 2:** `+971 58 520 2572` (`tel:+971585202572`)
- **Email 1:** `pots.decor@potsandplanters.ae` (`mailto:pots.decor@potsandplanters.ae`)
- **Email 2:** `uaepots@potsandplanters.ae` (`mailto:uaepots@potsandplanters.ae`)
- **Instagram:** `@plantersuae` — https://www.instagram.com/plantersuae
- **Business role:** Main Dubai showroom / warehouse; avoid claiming particular opening hours or services without evidence.

### Location 2 — REAL TOUCH FLOWERS

- **Section/display name:** `Real Touch Flowers`
- **Description:** `Our second showroom, Real Touch Flowers, is dedicated exclusively to premium artificial plants and trees by Real Touch.`
- **Address:** `UAE, Dubai, Jabal Ali Industrial First, Sheikh Saeed Bin Mohd Bin Hasher Ware House, Warehouse No. 163`
- **City/country:** Dubai, UAE
- **Phone:** `+971 58 520 2572` (`tel:+971585202572`)
- **Email:** `manager.realtouchtrees@gmail.com` (`mailto:manager.realtouchtrees@gmail.com`)
- **Instagram:** `@realtouch.uae` — https://www.instagram.com/realtouch.uae

### Location 3 — DESIGN HOUSE PROJECTS

- **Section/display name:** `Design House Projects`
- **Description:** `Our third showroom, Design House Projects, is dedicated exclusively to premium artificial plants and trees.`
- **Address:** `RHGA4460, 6824 Al Imam Saud Ibn Abdul Aziz Branch Rd, 4460, Riyadh 12282`
- **City/country:** **Riyadh, Saudi Arabia** — **NOT Dubai or UAE**.
- **Phone:** `+966 54 477 7707` (`tel:+966544777707`)
- **Instagram:** `@designhouseprojects` — https://www.instagram.com/designhouseprojects/
- **Email:** Not supplied; **do not invent one**.

### Implementation rules for these details

1. All three locations must appear on Contacts, with separate cards or sections. Do not silently omit Riyadh because the brand's primary market is the UAE. Make city/country visibly distinguishable (`Dubai, UAE` versus `Riyadh, Saudi Arabia`).
2. Keep per-location phone numbers, emails and Instagram handles attached to the correct showroom; don't accidentally reuse Dubai's main contact information for Riyadh or Real Touch.
3. The second showroom's `+971 58 520 2572` is intentionally shared with the first showroom; preserve this rather than deduplicating it away.
4. Show multiple phone/email links on the first card. Show no email link on the third card. All `tel:`, `mailto:` and Instagram links must be clickable and accessible.
5. **Get Directions:** Generate a map-search URL from each supplied full address (e.g. Google Maps search with an encoded query) or reuse a verified link found in the repo. A search URL is not a verified map pin: do not invent coordinates, place IDs or promise an exact entrance location. Open external links safely.
6. **Hours:** None provided. Do not display guessed business hours or `Open now` status.
7. **WhatsApp:** The phone numbers above are confirmed as telephone contacts, **not** as WhatsApp-enabled numbers. Only show WhatsApp using the existing verified site configuration; do not automatically turn any phone number into a `wa.me` URL.
8. **Primary contact display:** Use the established company-wide contact configuration where it exists; if it conflicts with the details above, avoid silent overwrites and report the conflict. For showroom cards, the data above is authoritative.
9. Use shared typed showroom data rather than hardcoding values in JSX; retain the ability to replace it with Filament/API data later. Reuse existing icon components instead of copying the old site's footer SVG assets unnecessarily.
10. Do not infer that all three locations belong to the same legal entity. Present them as showrooms/associated showroom brands per the supplied labels, without adding unsupported legal claims.

### Additional acceptance checks

- Exactly three supplied showrooms render, including the **Riyadh, Saudi Arabia** location.
- Main Dubai location shows **two phones and two emails**.
- Real Touch shows its dedicated email and Instagram.
- Design House Projects shows its Saudi phone and Instagram, **without a fabricated email**.
- Each `Get Directions` action uses the corresponding full address; no swapped destinations.
- Existing `ContactEnquiryForm` is reused unchanged and mock submission remains honest.
