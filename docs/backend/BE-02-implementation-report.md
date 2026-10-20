# BE-02 — Static Pages SEO Management: implementation report

## Initial SEO values preserved

The values below were copied from the seven page modules before changing their metadata integration. Open Graph and Twitter were not explicitly configured in either page metadata or the root layout. Robots were not explicitly configured, so Next.js keeps its default behavior. The root layout supplies `metadataBase` (`https://potsandplanters.ae`), icons and manifest; each selected page overrides its root title and description.

| Key | Route | Original title | Original description | Original canonical |
|---|---|---|---|---|
| `home` | `/` | Pots & Planters for Projects across the UAE \| Dubai | Pots, planters and artificial greenery for residential and commercial projects across the UAE. Discuss your project requirements with our Dubai team. | Not set; BE-02 adds `/` |
| `shop` | `/shop` | Planters & Artificial Plants Supplier in UAE \| Pots & Planters | Explore pots, planters and artificial greenery. | `/shop` (`SHOP_PATH`) |
| `solutions` | `/solutions` | Planters & Greenery Solutions for Hotels, Offices & Villas \| Pots & Planters | Explore planters and artificial greenery for hotels, restaurants, offices, villas and commercial spaces in Dubai and the UAE. Discover interior concepts and request a quotation. | `/solutions` |
| `catalogues` | `/catalogues` | Product Catalogues \| Pots & Planters UAE | Explore catalogues of outdoor and indoor planters, artificial plants and trees for hospitality, commercial and residential projects across the UAE. | `/catalogues` |
| `about` | `/about` | About Pots & Planters \| Planters & Greenery for Projects | Discover how Pots & Planters helps architects, designers and project buyers explore indoor and outdoor planters and artificial greenery for projects in the UAE. | `/about` |
| `contacts` | `/contacts` | Contact Us \| Pots & Planters | Discuss planters and artificial greenery for your project. Contact our team and explore showroom contacts in Dubai, UAE and Riyadh, Saudi Arabia. | `/contacts` |
| `private_policy` | `/private-policy` | Privacy Policy \| Pots & Planters | Learn how Pots & Planters handles information related to website enquiries, product selections, catalogue requests and essential website storage. | `/private-policy` |

`/news` remains outside the whitelist as required. It is a separate static index page and needs a product decision before it is added.

## Implementation

- Added `page_seos`, a unique `page_key`, nullable public OG image path, and an idempotent `PageSeoSeeder`. Existing edits are preserved on repeated runs. `DatabaseSeeder` now invokes only this safe, missing-rows-only seed.
- Added the authenticated Filament `Page SEO → Static Pages` resource. It exposes only list/edit routes, hides create/delete operations, makes keys non-editable, displays fixed canonical URLs, and accepts JPEG/PNG/WebP images up to 5 MB on the public disk.
- Added the public read-only `GET /api/v1/page-seo/{pageKey}` API. It returns only `pageKey`, `title`, `description`, and `ogImageUrl`, rejects unknown keys with 404, and sends `Cache-Control: no-store, private`.
- Added a model-agnostic `SeoSearchPreview::make(titlePath, descriptionPath, urlPath)` Filament field helper. It reads reactive form values, uses escaped Blade output, wraps long text, and shows the required preview disclaimer.
- All seven Next.js pages call Laravel through `serverApiFetch` from `generateMetadata`; they render dynamically at request time. API errors are logged and rethrown, with no SEO mock fallback. Canonicals use fixed route paths and the root production `metadataBase`. Open Graph and Twitter fields follow the current SEO values and include an uploaded image only when one exists.

## Verification

- Created a dedicated `potsandplanters_test` MariaDB database. `backend/phpunit.xml` selects it for the test process; `AGENTS.md` documents setup and test commands.
- Backend suite: 13 tests / 55 assertions passed on `potsandplanters_test`. Coverage includes repeatable seed, public API contract/404 and public OG URL, and a Filament Livewire edit/save that checks reactive escaped preview output and prevents `page_key` changes.
- Frontend TypeScript check passed.
- Next.js production build passed. Build output marks all seven selected routes as dynamic (`ƒ`), so they do not bake database metadata into a static build.
- PHP lint and Laravel route discovery passed for the API and Filament resource.
- Runtime HTTP checks returned 200 for all seven pages with canonical, Open Graph, and Twitter metadata. After changing only the About title in the test database to `BE-02 SSR verification`, `/about` returned that title in its server-rendered `<title>`.

## Remaining checks

The Filament save and server-rendered HTML checks passed through separate test/runtime steps against the dedicated test database; a manual browser session combining those steps has not been run. The public OG image upload/replace/remove path also needs browser verification. Deployment must run the migration and seed against its own database before serving these routes. Never point these test commands at production.
