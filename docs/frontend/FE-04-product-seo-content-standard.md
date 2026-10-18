# Pots & Planters 2.0 — Product SEO Content Standard

**Document:** FE-04-SEO-01  
**Status:** Proposed content standard and frontend integration requirements  
**Scope:** Product detail pages, English-language UAE market, B2B-first with retail supported

## 1. Goal

Give every published product page useful, factual, distinct product information while retaining a clean premium UI. Explain what the product is, its verifiable characteristics, where it can be used **when supported by data**, and how project customers in Dubai and the UAE can enquire. Avoid templated keyword stuffing and invented technical claims.

This is a content and rendering standard, **not** a promise of rankings or a replacement for the final SEO audit.

## 2. Placement and page hierarchy

On the product page:

1. Breadcrumbs
2. Gallery and purchase/enquiry panel (H1 = human-readable product name; SKU, price, specifications, quantity and CTAs)
3. **Product Description** (new section; one H2 and normally two paragraphs)
4. Related Products
5. Footer

Do not add a large generic UAE promotional banner to each product. Keep the text left-aligned, readable, and about 700–900 px wide on desktop; mobile should use the normal page gutters. Description text must be rendered in server HTML and visible without expanding an accordion. If a product lacks sufficient verified information, publish a shorter factual description or hold the copy for review; do not fabricate details to reach a word count.

## 3. Product information model (future Laravel/Filament contract)

Required for a publishable product: `id`, `sku`, `name`, `slug`, `categoryIds`, `images` (at least one suitable product image, subject to editorial policy), `isPublished`.

Structured optional attributes: `diameterCm`, `heightCm`, `widthCm`, `depthCm`, `material`, `finish`, `color`, `shape`, `usage` (indoor/outdoor only if verified), `features` (verified facts), `collection`, `variants`, `priceAed` (nullable), `availability` (nullable), `deliveryEligibility` (nullable and verified).

Editorial fields:

- `shortDescription` — optional, factual, brief.
- `descriptionHeading` — optional H2, usually generated from product name and a genuine distinctive property.
- `descriptionParagraphs` — 1–3 edited paragraphs; default 2.
- `seoTitle`, `seoDescription` — optional editorial overrides.
- `ogImage` — optional; fall back to main product photo.
- `canonicalOverride`, `noindex` — advanced SEO controls for stage 4.
- `contentReviewStatus` — `draft | needs_review | approved` (planned for stage 3; not required in frontend mock implementation).

**Important:** product name must not contain concatenated dimensions/finish solely for display. Keep name, SKU, dimensions and finish as distinct fields; generate formatted spec strings from data.

## 4. Writing requirements

### H1

Use the product's actual name, e.g. `Coral Planter`. Do not stuff city, country, material or dimensions into H1 unless part of the actual product name.

### Description H2

A natural descriptive heading, e.g. `Coral Planter with Textured Bronze Patina`. A heading like `Coral Planter — Bronze Finish for Residential & Commercial Spaces` is acceptable **only if that usage is verified**. The H2 should not repeat the same SEO boilerplate across every SKU.

### Paragraph 1 — product-specific

Typically 35–75 words. Describe only known facts: shape, visual style, finish, verified material, actual dimensions, variants, and legitimate use cases. This paragraph should be distinct across products. Do not infer composition or durability from a photograph.

### Paragraph 2 — project relevance

Typically 30–65 words. Explain how this specific product could fit relevant verified applications, and invite architects, interior designers, hospitality procurement or project teams to request quantities/specifications. Mention Dubai/UAE naturally if appropriate. The company's geographic service statement may be reused sparingly, but vary the sentence structure and ensure the paragraph contributes product-specific information.

### Length

A normal product needs about **70–140 words total**, not a rigid quota. A simple SKU may need less. Avoid padding with repeated claims. Category pages carry broader UAE/B2B positioning; product pages should primarily describe the product.

### Tone

Professional, clear, premium, specific, British/international English suitable for UAE customers. Avoid hype (`best`, `luxury quality`, `perfect for every project`) and unsupported guarantees (`weatherproof`, `UV-resistant`, `fire-rated`, `in stock`, `fast delivery`, `made in Italy`, `wholesale discounts`).

### B2B signals

Use contextually, not as a mandatory checklist: architects, interior designers, contractors, hotel/restaurant procurement, villas, commercial interiors, landscaping, multi-unit projects, project quantities, specification requests, Dubai, UAE. **Do not claim an application that is not supported by product usage data.**

## 5. Example: Coral Planter (SKU 25526)

**Verified from current mock page/screenshot:** name `Coral Planter`; SKU `25526`; dimensions `Ø 67 × H 67 cm`; finish `Bronze patina`. The image shows a rounded tapered vessel. Material, indoor/outdoor rating and delivery terms are not established by the screenshot.

**H1:** Coral Planter

**H2:** Coral Planter with Textured Bronze Patina

**Paragraph 1:** The Coral Planter has a rounded, tapered silhouette and a textured bronze patina finish. With a diameter of 67 cm and a height of 67 cm, it brings a sculptural accent to spaces where the planter itself forms part of the design.

**Paragraph 2:** Pots & Planters works with architects, interior designers and project teams across Dubai and the UAE. Contact our team to confirm suitability for your intended setting, discuss quantities and request the latest product specifications.

**SEO title (draft):** Coral Planter 25526 — Bronze Patina | Pots & Planters UAE

**Meta description (draft):** Explore Coral Planter 25526 with a textured bronze patina finish, Ø 67 × H 67 cm. Enquire about specifications and project quantities in Dubai and the UAE.

**Alt text (main product photo):** Coral Planter 25526 with textured bronze patina finish, front view

If the photograph does not actually show a front view, adjust the alt text. Do not use the same alt text for every gallery image.

## 6. SEO metadata and technical rules

- Product detail H1 = real product name, exactly one primary H1.
- Each product has a stable, crawlable, canonical URL. Avoid multiple canonical pages for the same SKU just because it appears in multiple categories.
- Generate a unique SEO title and description using verified attributes. Aim for concise, human-readable snippets; search engines may rewrite them.
- Prefer descriptive image alt text for meaningful product photos, empty alt for purely decorative images. Do not stuff location keywords into every alt.
- Product description must be included in server-rendered HTML (Next.js), not solely injected after client hydration.
- Breadcrumbs should reflect the selected or primary category consistently.
- Do not claim `InStock`, numeric price, delivery estimates or review ratings unless the corresponding source data is verified.
- JSON-LD (`Product`, `BreadcrumbList`, relevant `Offer` where eligible) is part of **stage 4**. A `Price on enquiry` product must not receive a fabricated price or misleading `Offer` markup.
- Product content does not replace category SEO text. Category pages address broader product types, industries and UAE supply; product pages address individual models.

## 7. AI draft generation policy (Qwen / Codex / other model)

Generate **drafts**, not automatic live publications. Feed the model a structured, verified product record. Require structured JSON output, then validate and review before publication.

### Generation input

```json
{
  "sku": "25526",
  "name": "Coral Planter",
  "dimensions": {"diameterCm": 67, "heightCm": 67},
  "finish": "Bronze patina",
  "material": null,
  "usage": [],
  "verifiedFeatures": [],
  "company": {
    "name": "Pots & Planters",
    "baseCity": "Dubai",
    "serviceArea": "UAE",
    "projectEnquiries": true
  }
}
```

### Generation instruction (reusable prompt)

> Write factual English SEO content for one product page on Pots & Planters, a Dubai-based supplier serving project customers across the UAE. Use ONLY the supplied verified product facts. Never infer material, indoor/outdoor suitability, weather resistance, availability, lead time, country of origin or delivery guarantees. Produce a unique H2, two concise paragraphs focused first on the product and then on legitimate project-enquiry relevance, a concise SEO title, a meta description and alt text for the provided image description. Do not repeat generic sales language or keyword-stuff Dubai/UAE. If facts are insufficient, write less and include `missingFacts` questions. Return JSON only.

### Expected output schema

```json
{
  "h2": "...",
  "paragraphs": ["...", "..."],
  "seoTitle": "...",
  "seoDescription": "...",
  "imageAlt": "...",
  "missingFacts": [],
  "warnings": []
}
```

### Validation before publishing

1. Every material, size, colour, usage and origin claim matches source data.
2. No unsupported durability, stock, shipping, certification or installation promises.
3. Text differs meaningfully from other products; no mechanically duplicated paragraphs.
4. H1, H2, title, description and alt accurately describe the SKU.
5. Spelling, clarity and commercial tone are appropriate.
6. Human editor approves content; do not auto-publish AI drafts.

## 8. Codex implementation task — FE-04-SEO-01

**Implement now (frontend only):**

1. Inspect current product page, mock product model and existing SEO text patterns; preserve all manual edits.
2. Extend typed mock product data with `descriptionHeading`, `descriptionParagraphs`, and optional SEO fields, without breaking catalog filters, product cards or related products.
3. Add reusable `ProductDescription` component below gallery/specifications/CTAs and above Related Products. Use semantic `<section>`, `<h2>` and `<p>` elements.
4. Populate Coral Planter with the verified example above. For other mock products, write descriptions **only when data supports them**; otherwise omit or use a shorter factual text. No fabricated features.
5. Ensure mobile layout is readable and there is no duplicate H1 or product description.
6. Verify the text is server-rendered and visible in HTML; do not hide it behind an accordion.
7. Run existing TypeScript/build checks and report results.
8. Produce `docs/frontend/FE-04-SEO-01-report.md` listing changed files, products updated, assumptions and remaining data gaps.

**Do not implement now:** Laravel/Filament fields, bulk AI generation, Qwen integration, schema.org, redirect mapping, checkout, search or changes to global infrastructure.

## 9. Acceptance criteria

- [ ] Product description appears after primary product details and before related products.
- [ ] H1 remains the model name; new content has one meaningful H2.
- [ ] Coral copy matches verified SKU, dimensions and finish.
- [ ] No unsupported material, usage, delivery or stock claims.
- [ ] Description content comes from typed data, not hardcoded JSX.
- [ ] Content is present in server-rendered HTML.
- [ ] Mobile typography and spacing remain readable.
- [ ] Existing gallery, quantity controls, enquiry actions and related products work.
- [ ] TypeScript and production build succeed.
- [ ] Implementation report documents data gaps and validation.

## 10. Later stages

**Stage 3:** add Filament editorial fields, optional Qwen draft-generation action, explicit review/approval state, and API delivery of product content.  
**Stage 4:** complete SEO keyword research, redirects, canonical policy, structured data, sitemap, Search Console checks, and verification of indexation/performance.
