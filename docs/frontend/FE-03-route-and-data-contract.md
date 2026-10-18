# FE-03 — Route and data contract

## FE-03.1: inspection and implementation plan

Reviewed the current Next App Router, Mantine theme, SiteShell, HomeLayout, header/mobile navigation, floating controls, homepage fixtures/cards, legacy `product/category.tpl`, legacy home links and category desktop/mobile screenshots. Existing homepage and Layout are preserved. Only category targets in `config/site.ts` change. The old category's olive palette, breadcrumbs, sidebar and product presentation guide the new UI; no legacy runtime is imported.

Sequence: (1) typed domain, centralized fixtures and selectors; (2) shared listing and bounded product placeholder; (3) permanent desktop navigation/filters and Mantine mobile Drawer; (4) URL normalization/history, domain checks, typecheck/build and responsive validation.

Route reservations checked against current `app/`: `/shop`, `/shop/outdoor-pots`, `/shop/indoor-pots`, `/shop/artificial-plants`, and `/product/[slug]` do not collide with `/`, `/search`, or `/catalogues`. Category routes resolve only from the centralized catalogue route map; unknown paths return 404. The category structure is flat under `/shop`; previous standalone category URLs and `/shop/art-plants` permanently redirect to their new paths.

## Routes

The category map is `frontend/src/config/catalog.ts`. Both shared navigation and the mock repository consume it. `SHOP_PATH` and `productPath()` are in `lib/catalog/domain.ts`. The `/shop/[category]` route resolves only registered category paths.

| Path | Category ID | Parent | Status |
| --- | --- | --- | --- |
| `/shop` | — | — | All published products |
| `/shop/outdoor-pots` | outdoor | `/shop` | Category section |
| `/shop/indoor-pots` | indoor | `/shop` | Category section |
| `/shop/artificial-plants` | artificial | `/shop` | Category section |
| `/outdoor-pots`, `/indoor-pots`, `/art-plants`, `/artificial-plants`, `/shop/art-plants` | — | — | Permanent redirects to `/shop/[category]` |
| `/product/[slug]` | — | — | Known published fixture only; bounded interim message for FE-05 |

Unknown category paths, unknown products and unpublished product slugs return 404. `/catalogues` remains the FE-07 placeholder. `/search`, homepage and shared Layout are preserved. Existing homepage product links resolve through the interim product route.

## Query schema and history

| Parameter | Accepted values | Default |
| --- | --- | --- |
| `material` | Category fixture's facet values | None |
| `color` | Category fixture's facet values | None |
| `size` | Category fixture's facet values | None |
| `usage` | Category fixture's facet values | None |
| `sort` | `featured`, `name-asc`, `name-desc`; `price-asc`/`price-desc` only where numeric prices exist | `featured` |
| `page` | Safe positive integer; clamp to last available page | 1 |

Canonical query serialization uses one parameter per group with comma-separated, sorted, unique values. `URLSearchParams` encodes commas as `%2C`. Repeated keys are accepted on input and merged. Filter keys serialize in Material, Color, Size, Usage order, then sort and page. Unknown keys, unavailable facet values, invalid sorts and malformed pages are ignored. Default sort and page 1 are omitted. Noncanonical queries are replaced in browser history after hydration without scrolling. This URL normalization is separate from SEO canonical metadata.

Example: `/shop/outdoor-pots?material=fiberglass&color=black%2Cbronze&sort=name-asc`.

- Category links navigate to the category's bare path: all filters, sort and page reset.
- Filters combine AND across groups and OR within a group. Filters/sort changes reset page to 1.
- Clear filters keeps the category and current sort, removes attributes, and resets page to 1.
- Desktop checkboxes apply immediately, with optimistic selected state and a loading status. Focus remains on the checkbox. Pagination and query changes use navigation without forced scrolling.
- Mobile Drawer opens with a copy of applied filters. Checkboxes and Clear filters change that draft; Show products commits it once. Closing/Escape discards unapplied changes. Category links navigate immediately.
- Applied filter buttons remove individual values. Query changes push history; Back/Forward, refresh and direct links reproduce the listing.
- Page size is fixed at **12**. No-result listings use page 1 and show clear filters. `/shop` has 16 published fixtures (12 + 4); Outdoor has 9, Indoor has 11 and Artificial Plants has 2.
- Per-page size is not a public query parameter. Only the current page's products are passed to the listing client island/rendered as cards.

## Domain and fixture contract

`types/catalog.ts` defines Category, Product, CatalogQuery, CatalogResult, CatalogFacet and CatalogRepository. Category IDs are distinct from slugs. Categories are top-level; products have `categoryIds: string[]` and may belong to more than one category. Category navigation is separate from attribute selection.

Attributes currently use arrays of stable lowercase values for Material, Color, Size and Usage. Facets derive from all **published products in the selected category**, before selected filters. This keeps selected options available and makes valid empty combinations possible. An individual value always matches scoped products; combinations can produce zero matches. Facet counts are not advertised. Future API values must use the same stable query keys or provide a deliberate migration.

`data/catalog.mock.ts` adapts the eight accepted homepage fixtures without mutating them and adds eight explicitly labelled sample items plus one unpublished test fixture. Only the first four homepage names/SKUs are transcribed historical products. PP-520–PP-523 and DEMO-* are illustrative. All category assignments and attributes are illustrative; a notice on listings states this. Products may belong to more than one top-level category.

Photographs use existing permitted `/images/home/products/product-*.webp` assets. Additional sample products have no photograph and use the stable existing image-pending placeholder. No photo is represented as evidence of a different real product. Descriptive names provide image alt text; missing images get the visible Image pending label.

Published mock products have illustrative AED prices from 100 to 2,000, as requested; these are demo values and do not represent current commercial prices. Cards also support null (Price on enquiry). Price sorting is enabled for the mock data; enquiry items sort last in either direction, numeric ties by ID. Featured ordering is deterministic: featured first, then stable fixture order; name sorts use English comparison with ID ties. No stock, discounts, delivery promises or ratings are added.

## Laravel replacement boundary

`lib/catalog/repository.ts` implements the async `CatalogRepository`:

- `getCategories()` returns the three top-level categories.
- `getCategoryByPath(path)` resolves a registered path.
- `getProductBySlug(slug)` returns a published fixture for the bounded interim route.
- `getProducts(query)` returns current-page products, normalized page/page count/total, category-scoped facets and available sorts.

UI code imports this interface/adapter and pure URL helpers, never fixture arrays. The server `CatalogPage` fetches category scope metadata, validates URL values against that scope, then fetches the requested listing. A future HTTP adapter can optimize these two calls with category facet metadata/caching. It must preserve category membership, deduplication, publication visibility, AND/OR semantics, stable sorts, enquiry ordering and normalized pagination. Backend remains authoritative for future publication and prices; do not trust client input. No Laravel routes, endpoints or schema are prescribed or implemented here.

## Metadata extension points and later stages

Shop and category pages supply title, description and a query-free route canonical through Next metadata. The canonical currently resolves against the serving origin; production origin/indexing policy, filtered URL robots policy, legacy redirects, schema.org and sitemap belong to stage 4. Attribute combinations have no new category paths or identities. The interim product page is noindex and intentionally has no product-detail features. FE-04 supplies search independently; FE-05 replaces the product placeholder and must confirm proposed product slugs; FE-06 supplies cart/inquiry actions; FE-07 supplies PDF catalogues.
