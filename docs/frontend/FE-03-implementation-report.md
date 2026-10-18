# FE-03 — Implementation report

Date: 2026-10-18. Scope: existing Next.js/Mantine frontend, local mock catalogue.

## Completed sequence

1. **FE-03.1**: inspected current routing, approved theme/Layout/homepage, cards, navigation and floating actions; reviewed OpenCart category/home templates and desktop/mobile category screenshots. Recorded the implementation plan and collision-free route map in the route/data contract before implementation.
2. **FE-03.2**: added typed domain, centralized flat categories, read-only homepage fixture adapter, additional labelled sample fixtures, asynchronous repository boundary, category selection/deduplication, dynamic facets, AND/OR filters, stable sorting and 12-item pagination. Typecheck passed before listing work.
3. **FE-03.3**: added Shop/category pages with breadcrumbs, headings, introduction, result range, card grid, pagination and explicit sample-data notice. Cards retain the established typography/olive palette and image treatment, provide enquiry pricing and graceful missing images. Known product links resolve to a bounded placeholder for FE-05. No product detail implementation was added.
4. **FE-03.4**: added a permanent 250 px desktop sidebar, flat category links with current-page semantics, attribute groups, applied filter removal and reset. Mobile/tablet uses Mantine Drawer, a local draft, Clear filters/Show products, independent scrolling and fixed visible actions. Drawer layer 300 stays above the existing floating layer 100. Typecheck passed with listing/sidebar/Drawer.
5. **FE-03.5**: connected deterministic URL parsing/serialization, invalid-query replacement, optimistic checkbox state, history navigation, loading/error/empty states and route metadata extension points. Completed domain tests, build, real Firefox checks and screenshots.

## Files

- `frontend/src/types/catalog.ts`: domain and asynchronous repository contract.
- `frontend/src/config/catalog.ts`: single category/route map.
- `frontend/src/data/catalog.mock.ts`: centralized catalogue fixtures, non-mutating homepage adapter.
- `frontend/src/lib/catalog/{domain,repository}.ts`: pure selectors/URL helpers and local repository.
- `frontend/src/components/catalog/{CatalogPage,CatalogListing,CatalogCard}.tsx` and `Catalog.module.css`: server composition, client interactions and responsive presentation.
- `frontend/src/app/shop/{page,loading,error}.tsx`: Shop route and bounded loading/error states.
- `frontend/src/app/shop/[category]/{page,loading,error}.tsx`: category sections under `/shop`; `frontend/src/app/[category]/page.tsx` permanently redirects the previous standalone paths.
- `frontend/src/app/product/[slug]/page.tsx`: known published product placeholder.
- `frontend/src/config/site.ts`: only the three existing category link targets changed.
- `frontend/src/app/catalogues/page.tsx`: removed the obsolete sentence claiming category links still target the PDF placeholder.
- `frontend/package.json`: added `test:catalog`, no dependencies changed.
- `frontend/tests/catalog.test.cjs`: domain/fixture regression checks using the installed TypeScript compiler.
- `docs/frontend/FE-03-route-and-data-contract.md`, this report and `docs/frontend/fe-03-screenshots/*.png`.

The input specification `FE-03-catalog-categories.md` was already untracked when work began and was left unchanged. Existing homepage, home fixtures/styles, Layout components/styles, root layout and floating-button implementation were not edited. The only pre-existing tracked files modified are package.json, config/site.ts and the obsolete catalogues placeholder sentence. No backend, infrastructure or lockfile changes.

## Automated verification

Commands from the repository root unless stated otherwise:

| Command | Result |
| --- | --- |
| `npm run test:catalog --prefix frontend` | PASS, 8 tests |
| `npm run typecheck --prefix frontend` | PASS |
| `npm run build` from `frontend/` | PASS, production Next build including TypeScript and route generation |
| `git diff --check` | PASS |
| Lint | No lint script/config supplied by this project; not claimed as passed |

One additional build invocation from the repository root with `npm run build --prefix frontend` failed while Next parsed TypeScript `--showConfig`. Re-running the documented project command `npm run build` with `frontend/` as the working directory passed, including TypeScript and route generation. No type checking was bypassed.

Tests cover published visibility, 12+4 pagination, page clamping, flat category selection, products shared by categories, AND/OR filtering and accurate zero results, category-scoped facets, unknown URL values/repeated and comma selections/deterministic serialization, malformed pages, name ordering, conditional price sorts and enquiry-last ordering using test-only numeric values, unique routes/IDs/slugs and existing local photographs.

## Real browser verification

Used installed **headless Firefox** via WebDriver BiDi against the production build on a temporary local server at `127.0.0.1:3103`; no browser-testing packages or project dependencies were installed. Firefox and the server required permission to run outside the sandbox because Firefox crashed inside it and the sandbox disallowed opening a port.

| Width | Layout verified | Horizontal overflow |
| --- | --- | --- |
| 320 | Drawer trigger, one product column | None |
| 375 | Drawer trigger, two columns | None |
| 768 | Drawer trigger, two columns | None |
| 1024 | Permanent sidebar, three columns | None |
| 1440 | Permanent sidebar, three columns | None |

Screenshots: [320](fe-03-screenshots/outdoor-320.png), [375](fe-03-screenshots/outdoor-375.png), [768](fe-03-screenshots/outdoor-768.png), [1024](fe-03-screenshots/outdoor-1024.png), [1440](fe-03-screenshots/outdoor-1440.png). The 375 and 1440 screenshots were also visually inspected.

Verified actual rendered/browser behavior:

- `/shop?page=2` renders four cards; changing a filter resets pagination.
- Multiple colors combine with OR, update deterministic URL and result counts; checkbox focus survives server navigation.
- Changing sort works; browser Back and Forward restore sort and selections.
- Direct links/refresh preserve attributes; invalid values/default sort/out-of-range page normalize to valid URLs.
- Category routes render correct headings and current category.
- Empty combinations show zero cards and working Clear filters.
- Product links resolve to the bounded interim message.
- Drawer receives focus, retains unapplied draft without URL change, applies it once, closes on Escape and returns focus to its trigger.
- Drawer action buttons stay within the viewport and receive hit testing above floating controls.

Screen reader announcement behavior, physical touch devices and Safari/Chromium have not been tested. Loading/error components compile and are wired as App Router boundaries; no live transport failure was injected into the local mock repository. Full SEO policy and production canonical-origin verification remain stage 4 work.

## Data limitations and integration notes

There are 16 published products and one unpublished fixture: four historically sourced names/SKUs, four existing homepage sample entries and eight clearly marked additional sample entries. Attributes, category assignments and new child routes are demonstrative. Extra sample items use image placeholders rather than unrelated product photographs. Published mock products have illustrative AED prices from 100 to 2,000; they are demo values rather than confirmed commercial prices. Numeric rendering and price sorting are supported.

FE-04 should add search without coupling it to category attributes. FE-05 should replace `/product/[slug]` and confirm the proposed slugs; the current route intentionally contains only identity, an interim message and a catalogue link. No Laravel API, Filament, database, search, cart, checkout, PDF download system, structured data or sitemap was implemented. The async repository and documented contract are ready for a later backend adapter.
