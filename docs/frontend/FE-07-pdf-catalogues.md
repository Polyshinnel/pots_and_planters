# FE-07 — PDF Catalogues Page

## Objective
Implement the public `/catalogues` page for Pots & Planters, reusing the existing catalogue carousel/card data and `CatalogueDownloadForm` already implemented on the homepage. This is a frontend-only task on mock data. A catalogue click opens the download form **in a modal on the current page**; it must never navigate to a catalogue detail page.

## Before coding
1. Inspect the current Next.js/Mantine frontend, homepage catalogue carousel, `CatalogueDownloadForm`, modal integration, catalogue mock data, and existing category-to-catalogues links.
2. Follow existing code structure, components, theme tokens, breakpoint conventions, routing, and data contracts. Do not guess paths or rewrite working features.
3. Preserve all manual UI edits and avoid adding dependencies unless essential.

## Page `/catalogues`
- Reuse the global Header, Footer, floating actions, breadcrumbs, typography and brand styling.
- H1: **Product Catalogues**.
- Short B2B introduction (copy may be polished while keeping meaning): “Explore our product catalogues for outdoor and indoor planters, artificial plants and trees. Find ideas and suitable options for hospitality, commercial and residential projects across the UAE.”
- Filter controls: **All Catalogues**, **Outdoor Pots**, **Indoor Pots**, **Artificial Plants & Trees**. Match category identifiers/slugs to those already used in the app; do not introduce conflicting taxonomies.
- Show catalogues as a responsive grid of visually prominent PDF covers. Each card: cover, title, concise description if present, relevant category tags if useful, and **Download Catalogue** CTA. No invented metadata (page counts, file sizes, release dates, brands, etc.).
- A catalogue may belong to multiple categories. Filtering should include it in every matching category.
- Responsive: typically 3 columns desktop, 2 tablet, 1–2 mobile depending on available card width and existing visual language. Keep readable text and touch targets, no horizontal overflow.
- Empty filter state: a clear message and **View All Catalogues** action.
- Bottom B2B CTA: **Need help selecting products for your project?** Short copy about sizes, finishes, quantities and tailored recommendations; link to the existing contact route or configured WhatsApp channel (reuse existing helpers, no hardcoded contact details).

## Filter URL contract
- Support `/catalogues` and `/catalogues?category=<slug>`.
- Existing contextual catalogue links from product categories should preselect the corresponding filter. Inspect and reuse the current slug vocabulary rather than inventing new URL values.
- Updating a filter should update the query parameter, preserving browser back/forward navigation. **All Catalogues** removes `category`.
- Invalid or unknown `category` values fall back gracefully to All Catalogues; no crashes.
- Avoid hydration mismatches and unnecessary full-page reloads. Respect Next.js App Router and existing conventions.

## Interaction contract — most important
- On homepage carousel **and** `/catalogues`, clicking the catalogue cover, title, or Download CTA opens the **existing** `CatalogueDownloadForm` in a modal.
- The modal must receive the selected catalogue's stable identifier and show the selected catalogue's name/context.
- Reuse the existing form, validation, including mandatory **Who are you?** B2B role selection. Do not create a second form or divergent role options.
- Closing the modal keeps the user on the same page, retaining scroll position and selected filter. Escape key, backdrop and close button should work according to existing Mantine accessibility conventions.
- Do not route to `/catalogues/[slug]`, PDF preview pages, or a separate download landing page.
- Avoid nested click handlers causing double-open, and make cards keyboard accessible. A card can use one primary interactive target; prevent invalid nested button/link markup.
- Prefer a small reusable shared catalogue card/action/modal controller when that genuinely removes duplication; preserve homepage carousel layout and visuals.

## Data and mock boundaries
- Reuse existing catalogue mock records and types. Extend minimally if needed for stable `id`, cover, description, and multiple category references.
- If homepage and page currently use separate hardcoded datasets, consolidate them into one source without altering visible homepage content or ordering unexpectedly.
- Never invent downloadable PDF URLs or claim real files are available.
- **Do not simulate successful form submission or file download**: Laravel persistence, Telegram notifications, gated PDF delivery and access control are Phase 3.
- Retain existing FE-06 frontend-only form behavior, e.g. development payload preview or clearly disabled submission as currently implemented.
- No new backend/API endpoints, Filament, database tables, Telegram integration, or mock “success” screens.

## SEO and content
- Appropriate unique title and meta description for `/catalogues`, following current SEO patterns.
- One H1 and meaningful headings. Accessible labels/alt text for catalogue covers; avoid keyword stuffing.
- This is a catalogue listing, not individual SEO pages for PDF documents.

## QA / acceptance criteria
1. `/catalogues` renders and fits the existing visual system on desktop, tablet and narrow mobile.
2. Homepage catalogue carousel looks and behaves as before, except consistent shared modal behavior if previously inconsistent.
3. Click cover/title/Download on either surface -> modal with the **correct** selected catalogue and the existing form.
4. Form includes the existing required B2B role field and unchanged validation.
5. Modal closes without navigation, loss of filter, or scroll jump.
6. Direct URLs with supported `?category=` values select the right filter and show matching records; multi-category catalogues appear in each applicable category.
7. Filter changes update URL and back/forward navigation works; unknown values are handled safely.
8. No `/catalogues/[slug]` detail page is added.
9. No fabricated successful submission/download or clearing of form/cart state.
10. No regressions to product category CTA links, homepage, header, checkout, or existing forms.
11. TypeScript checks, lint (if configured), and production build pass; note any pre-existing failures distinctly.

## Delivery
- Implement in the existing repo, making minimal coherent changes.
- Provide a concise implementation report: modified files, reused components, filter slug mapping, mock/API boundary, manual QA and commands run.
- Flag missing genuine catalogue content/assets rather than inventing it.
