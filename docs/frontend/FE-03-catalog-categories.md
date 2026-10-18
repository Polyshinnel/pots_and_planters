# FE-03 — Catalog & Categories

**Project:** Pots & Planters 2.0  
**Stage:** Frontend only (Next.js + Mantine)  
**Status:** Implementation specification for Codex  
**Prerequisites:** INF completed; FE-01 Layout completed; FE-02 homepage accepted.

## 1. Objective

Implement a functional, responsive, B2B-oriented product catalogue with category navigation, attribute filters, sorting and pagination. The public catalogue must work with typed local mock data, without Laravel API, database integration or Filament models at this stage.

Preserve the existing approved visual language, layout, homepage and manual changes. Consult `references/opencart/` and desktop/mobile screenshots in `references/screenshots/` for visual reference. Do not copy legacy OpenCart scripts/styles into the Next.js runtime.

## 2. Scope and boundaries

**In scope**
- `/shop`: all published mock products.
- Category sections at `/shop/[category]` with stable human-readable URLs.
- Permanent desktop sidebar with flat category links and filters.
- Mobile filter/category Drawer.
- Product listing cards, result counts, empty/loading/error states.
- Query-driven filters, sorting and pagination.
- Typed mock catalogue and a swappable data-access interface.
- Working category links in shared navigation where already present.

**Out of scope**
- Real Laravel endpoints, Filament, migrations, OpenCart import.
- Full-text product/SKU search (FE-04).
- Product detail page implementation (FE-05).
- Shopping cart or inquiry processing (FE-06).
- PDF catalogues (FE-07).
- Complete SEO audit, schema.org, redirects and legacy URL migration (stage 4).
- Reworking homepage or global visual identity.

## 3. UX architecture

### 3.1 Desktop
- Breadcrumb, H1, short category intro, product count, sorting control.
- Two-column layout: **persistent left sidebar ~240–270 px**, flexible product grid to the right.
- Sidebar sections: **Categories** (hierarchical navigation), then applicable attribute filters such as Material, Color, Size and Usage. Actual groups must come from mock data; do not invent values unsupported by fixtures.
- 3 product columns at normal desktop widths; 4 only where card readability allows. Avoid tiny images or text.
- Do not make the entire long sidebar sticky; all filters must remain reachable by normal page scrolling.
- Active category and active filter values must be clearly distinguishable and accessible.

### 3.2 Mobile/tablet
- Product grid generally 2 columns where legible, falling back to 1 for very narrow widths if needed.
- Compact toolbar with result count, **Filters** trigger and Sort control.
- Mantine Drawer contains category navigation and filters, with visible Apply/Show products and Clear filters actions. Preserve scroll and keyboard usability; focus returns to trigger on close.
- Category navigation remains navigation to a separate route, not a checkbox filter. Applying attribute filters updates the current category URL query.
- Do not allow floating WhatsApp/Catalogues buttons to obscure the Drawer actions or critical controls; respect the already implemented global floating-button behavior.

### 3.3 Product cards
- Use current project card styles as baseline; product image, name, model/SKU, pricing display and clickable product link.
- The price presentation must support both a numeric AED price and `Price on enquiry`; **do not fabricate prices**.
- Responsive image ratios, sensible placeholders, no distorted images; descriptive alt text based on product name.
- Link to intended product route (e.g. `/product/[slug]`); if FE-05 is not implemented, use a clearly bounded placeholder route or graceful interim state, not a broken 404 from a primary CTA.
- Do not add fake stock, delivery guarantees, discounts or ratings.

## 4. Routing and URL state

- `/shop` — all products.
- Category sections use stable readable slugs beneath `/shop`, e.g. `/shop/outdoor-pots` and `/shop/indoor-pots`. Previous standalone category URLs permanently redirect to these paths. The concrete route map must be centralized, not hardcoded across components.
- A category click navigates to its category URL, updates H1/breadcrumbs/active sidebar and resets incompatible filters.
- Query parameters represent **attribute filters, sort and page**; choose stable, readable parameter names and document them, e.g. `?material=fiberglass&color=black&sort=name-asc&page=2`.
- Multiple selections per filter group must be supported with deterministic encoding; URL parsing must reject/ignore unknown values safely.
- Back/forward browser navigation restores listing state; direct deep links reproduce the same view; refreshing does not reset filters.
- Changing filters or sorting resets `page` to 1. Clearing filters preserves category route, removes filter params and resets pagination.
- Query state must not create a separate SEO-indexable category for every combination. **Only prepare metadata/canonical extension points here**; full SEO policy is handled in stage 4.
- Product listing pagination must work (client-side over mocks is acceptable). Avoid loading every mock product into the DOM if the list grows.

## 5. Category and attribute semantics

- Category navigation lists only the three top-level categories; subcategories are not used.
- Products may belong to **multiple categories** (`categoryIds: string[]`).
- Selecting a category shows products assigned to that category, deduplicated.
- **Category is not an attribute filter.** Category clicks navigate; Material/Color/Size/Usage filter the current listing.
- Filtering within category is intersection across groups (AND); multiple values inside a group use OR unless a different behavior is explicitly documented.
- Facet options must be derived from available mock product attributes or explicitly defined metadata; do not display options that silently do nothing.
- Filtered result count and empty-state messages must be accurate.

## 6. Data model and mock-data boundary

Create a typed frontend catalogue domain model and a data-access layer. Suggested shape (adapt to existing project conventions):

```ts
type Category = {
  id: string;
  slug: string;
  path: string;
  name: string;
  description?: string;
  image?: string;
};

type Product = {
  id: string;
  slug: string;
  name: string;
  sku: string;
  categoryIds: string[];
  image: string;
  images?: string[];
  attributes: Record<string, string | string[]>;
  priceAed?: number | null;
  published: boolean;
  featured?: boolean;
};

type CatalogQuery = {
  categoryId?: string;
  filters: Record<string, string[]>;
  sort: string;
  page: number;
  perPage: number;
};
```

- Use a single centralized fixture dataset and typed selectors/functions such as `getCategories()`, `getCategoryByPath()`, `getProducts(query)`; these should be replaceable by Laravel API calls in stage 3.
- Reuse **real, available** product names, SKUs and photographs from permitted local references where possible. If fixture values are demonstrative, label them in source/docs and never present invented project history, customer testimonials or business claims as real.
- Supply enough diverse fixtures to exercise nesting, multiple categories, all filter groups, sorting, pagination, no-results state and both price modes.
- Keep fixtures separate from UI components; no duplicated hardcoded product arrays in pages.
- Existing homepage featured products should not be overwritten or silently changed. Where feasible, offer a shared fixture adapter only if it does not disrupt the accepted homepage.

## 7. Sorting and pagination

Minimum sort options:
- Featured / default (stable deterministic order).
- Name A–Z.
- Name Z–A.
- Price low–high / high–low **only if meaningful numeric prices exist**; products without prices must be handled predictably and never assigned a fictional numeric value.

Pagination:
- Fixed documented page size (e.g. 12 or 18), page controls, result range/count.
- Out-of-range page normalizes to a valid page or shows a documented empty state.
- Navigation and filter changes should not unexpectedly lose keyboard focus or scroll users to arbitrary positions.

## 8. Accessibility and responsive quality

- Semantic H1, breadcrumb navigation, labeled filter controls, focus-visible styling.
- Keyboard-operable category links, checkboxes, sorting and pagination.
- Appropriate selected/current semantics for active categories.
- Drawer has accessible title, focus trap and close behavior through Mantine.
- Check widths at least 375, 768, 1024 and 1440 px; additionally verify narrow ~320 px viewport.
- Avoid horizontal overflow and tiny text. Honor existing global font and spacing conventions.
- Keep image layout stable to reduce layout shift.

## 9. Suggested implementation sequence

**FE-03.1 — Inspect existing code and references**  
Review current frontend routing, theme, cards, header links, fixture conventions, old OpenCart category screenshot and product imagery. Write a brief implementation plan and route map. Preserve user changes.

**FE-03.2 — Catalogue domain and fixtures**  
Implement types, centralized categories/products, selectors and filtering/sorting/pagination logic. Use the flat category list and deduplicate products assigned to several categories.

**FE-03.3 — Listing pages and cards**  
Implement `/shop` and category pages, breadcrumbs, headings, toolbar, product grid and interim product links.

**FE-03.4 — Desktop sidebar and mobile Drawer**  
Implement flat category links, attribute groups, selected state, reset/apply behavior and responsive interactions.

**FE-03.5 — URL state, polish and validation**  
Wire direct URLs, browser history, empty states, responsive checks, tests/build and documentation.

## 10. Acceptance criteria

1. `/shop` loads a catalogue using centralized typed mock data.
2. Direct category URLs load their own correct title, breadcrumb and filtered product set.
3. Sidebar remains visible on desktop; mobile uses a usable Drawer.
4. The three category routes work and category links stay flat.
5. Products assigned to several categories display correctly in each.
6. Attribute filters work, combine predictably and update the URL.
7. Direct link, refresh, Back and Forward preserve catalogue state.
8. Sorting works deterministically and resets pagination appropriately.
9. Pagination and result counts reflect actual filtered data.
10. Empty/no-results states offer a working clear-filters action.
11. Product cards are responsive and do not invent prices, ratings or availability.
12. Existing homepage, Layout, floating buttons and manual FE-01/FE-02 changes remain intact.
13. No Laravel/Filament/database/API changes are made.
14. Lint/typecheck/build pass using available project scripts; report missing scripts rather than claiming they passed.
15. Core interactions are checked at desktop and mobile widths; report what was and was not verified in a real browser.

## 11. Deliverables

- Implemented frontend code.
- `docs/frontend/FE-03-route-and-data-contract.md` — routes, query schema, category/product fixture contract, filtering semantics, Laravel replacement boundary.
- `docs/frontend/FE-03-implementation-report.md` — files changed, verification commands/results, screenshots if possible, known limitations and FE-04/FE-05 integration notes.

## 12. Strict instructions for Codex

- Work against the **current** repository state; inspect before changing. Do not overwrite manual modifications.
- Reuse installed Mantine and Tabler; do not add new dependencies without a demonstrated need.
- No legacy jQuery/OpenCart code, no invented real business claims.
- Do not implement full-text search, product detail, checkout, backend, schema.org or sitemap in FE-03.
- If a decision conflicts with current project conventions, document the conflict and make the smallest compatible change; ask before destructive architectural changes.
- Complete each substep with verification before moving on.
