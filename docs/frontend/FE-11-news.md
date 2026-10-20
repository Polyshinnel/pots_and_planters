# FE-11 — News: listing, article pages and migration of seven real publications

## Goal and scope
Implement `/news` and `/news/[slug]` in the existing Pots & Planters 2.0 Next.js App Router frontend, using the seven **real, user-supplied** historical news articles below. The site is B2B-oriented, but news must preserve original facts, dates and historical context. No invented demo news. This is frontend/mock-data phase: **do not implement Laravel API or Filament now**, but design the data contract for eventual backend management.

## Before implementation
1. Inspect the repository's existing Next.js routes, design system, Header/Footer, SEO conventions, content patterns, image utilities, and existing news assets. Preserve all manual edits.
2. Inspect `/home/andrey/Загрузки/PotsAndPlanters/news/` and identify the real filenames, extensions, dimensions and contents. The user says the images are numbered `1`, `2`, `3`, etc., corresponding to articles 1–7. Verify the mapping rather than assuming exact extensions or that each number contains only one file. If ambiguous, report it and do not silently attach the wrong image.
3. Copy the appropriate images into the frontend's established public/media location; do not modify or delete the source directory. Optimize for web only when appropriate, retaining visual quality and provenance.
4. Do not scrape or fabricate replacement article images if source files are available. If an image is missing, use an existing neutral fallback and report the missing item.

## Routes and UI
### `/news` listing
- Breadcrumb, restrained editorial hero with `News & Insights` or `News`, short B2B-relevant intro, responsive article grid consistent with Home/About/Solutions.
- Each card: real cover image, original title, original publication date, short factual excerpt, category and link to its article.
- Default order: descending `publishedAt` (two entries dated 2025-10-30 need deterministic secondary order; do not invent times). Optionally highlight a real B2B project article visually without falsifying chronology.
- Categories for presentation/filtering: `Projects & Collaborations`, `Company News`, `Events & Exhibitions` (or similarly concise labels). No separate category URLs necessary. Avoid empty filters. Seven items do not require artificial pagination.
- If filters are implemented, ensure accessible keyboard interaction and useful URL/state behavior without introducing a heavy dependency.

### `/news/[slug]` article
- One H1, original date, category, responsive cover, semantic article body with proper paragraphs/subheadings where present, breadcrumbs, back-to-news link, optional related articles using existing real data, and modest CTA to `/contacts` or `/shop`.
- Render article body in server-generated HTML. External links should remain clickable and safe (`rel="noopener noreferrer"` for new-tab links).
- No author, reading-time claim, or additional project images unless confirmed by source.
- No fake testimonials, product SKUs, customer endorsements or project metrics.

## Content fidelity policy
- Preserve the seven supplied original titles, substantive wording, names, dates, and links as the canonical migration source. Do not silently turn historical 2023–2025 announcements into present-tense current offers. Do not assert that a 2025 exhibition is upcoming in 2026.
- Light presentation normalization (e.g. `Pots&Planters` → `Pots & Planters`, paragraph breaks, typo fixes) is optional, but must not alter meaning or factual claims. Prefer preserving article text verbatim, with original copy stored in a data module so later editorial review is possible.
- User-supplied claims about prominent clients, partnerships, scientific methods and services are historical company statements, **not independently verified facts**. Attribute them to the company's original announcement; do not embellish. If publication policy requires verification, flag it in handoff rather than inventing confirmation.
- Article 4 describes delivery and designer arrangement as a 2024 announcement; do not advertise it as a guaranteed current service elsewhere in the UI.
- Article 5 concerns INDEX Dubai **27–29 May 2025**, stand **6E272**. Preserve this as a past event.
- News dates are dates of publication, not dates of project completion.

## Content records — source of truth

### 1. Sheikh Omar bin Zayed Al Nahyan
- **Title:** Pots & Planters becomes the supplier for the private office of Sheikh Omar bin Zayed Al Nahyan
- **Published:** 2023-09-23
- **Suggested slug:** `supplier-private-office-sheikh-omar-bin-zayed-al-nahyan`
- **Category:** Projects & Collaborations
- **Image mapping:** source numbered `1`

Pots&Planters takes great pride in being selected to enhance the beauty and tranquility of the Sheikh's courtyard. This partnership represents a significant milestone for our company, further solidifying our reputation as a trusted and dependable provider of high-quality products in the landscape design industry.

Our extensive range of pots, planters, and accessories showcases elegant designs that cater to various aesthetic preferences. From ceramic pots to fiberglass planters, we offer a diverse selection to meet the diverse needs of our customers. Moreover, our customization services allow us to create unique and personalized products tailored specifically to our customers' requirements.

Our collaboration with the Sheikh's courtyard serves as a testament to our ability to meet the exacting demands of esteemed clients. We view pots as art and strive to ensure the satisfaction of each client.

### 2. Ficus Design and Greenpact Future Lab
- **Title:** Pots&Planters collaborates with Ficus Design and Greenpact Future Lab
- **Published:** 2023-09-29
- **Suggested slug:** `collaboration-ficus-design-greenpact-future-lab`
- **Category:** Projects & Collaborations
- **Image mapping:** source numbered `2`

Our company as the leading provider of high-quality pots and planters in UAE, is proud to announce a groundbreaking collaboration with Ficus Design and Greenpact Future Lab, two renowned companies specializing in sustainable design and environmental solutions.

Based on international scientific research and years of experience, Greenpact future lab (Barcelona) and Ficus (Moscow) have developed the Happiness Impact+ (HI+) - a tool for assessing the impact of green spaces on a person's emotional well-being, capable of not only measuring but also adjusting the indicators of this influence. The result is a kind of "smart green office" - an indoor greening methodology aimed at optimizing a company's return on investment (ROI) and key performance indicators (KPIs).

The collaboration aims to combine the expertise and resources of all three companies to create innovative and environmentally-friendly solutions for urban green spaces. With the increasing need for sustainable living and the growing importance of green spaces in urban areas, this partnership is set to revolutionize the way people interact with nature.

For more information please visit:
- https://greenpactlab.io/
- https://ficus-design.ru/

### 3. Larger showroom
- **Title:** Big News: We've Moved to a Bigger Showroom!
- **Published:** 2024-09-04
- **Suggested slug:** `moved-to-bigger-showroom-al-quoz`
- **Category:** Company News
- **Image mapping:** source numbered `3`

At Pots & Planters, growth is in our DNA. We've outgrown our old space and are thrilled to announce our move to a larger, more expansive showroom at Warehouse #4, Al Kahraman Street #4, Al Quoz Industrial 4, Dubai.

Why the move? Because you demanded more, and we delivered. Our new showroom is packed with the latest styles and an extensive stock of premium pots and planters, catering to the most refined tastes. Quality and style remain our top priority.

Come visit us and discover your next favorite piece!

### 4. Service announcement
- **Title:** Latest from Pots & Planters UAE!
- **Published:** 2024-09-18
- **Suggested slug:** `latest-from-pots-and-planters-uae`
- **Category:** Company News
- **Image mapping:** source numbered `4`

So, we’ve just launched something rather fabulous. Not to toot our own horn (well, maybe a little), but it’s pretty exciting.

Here’s the deal:

You Pick: Choose your favorite planters and lifelike plants from our utterly gorgeous collections. Perfect for sprucing up any corner of your home or office.

We Deliver: Yes, really. Not only do we bring them right to your door, but our designer will pop round to help you arrange everything perfectly. Because why should you lift a finger?

Tailored Perfection: Can’t decide on a look? Don’t panic. We’ll shuffle things around until you’re absolutely thrilled.

So, if you're looking to upgrade your space without the faff, we’ve got you covered. #NewService #LuxuryLiving #EffortlessDesign #PotsAndPlanters

### 5. INDEX Dubai 2025
- **Title:** Pots&Planters will take participation at INDEX Dubai 2025
- **Published:** 2025-05-26
- **Suggested slug:** `pots-and-planters-index-dubai-2025`
- **Category:** Events & Exhibitions
- **Image mapping:** source numbered `5`

Exiting news

Pots&Planters will take participation at INDEX Dubai 2025, the region’s leading interior design and fit-out exhibition.

27–29 May 2025

Dubai World Trade Centre

Visit us at stand number 6E272

We’re bringing our latest collection of planters and design-forward green solutions – perfect for elevating any interior or outdoor space.

Whether you're an architect, designer, buyer, or plant lover – we'd love to meet you. Let’s connect, get inspired, and grow together.

See you soon!

### 6. Jumeirah Marsa Al Arab
- **Title:** Pots&Planters becomes the supplier for the Jumeirah Marsa Al Arab Hotel in Dubai.
- **Published:** 2025-10-30
- **Suggested slug:** `supplier-jumeirah-marsa-al-arab-dubai`
- **Category:** Projects & Collaborations
- **Image mapping:** source numbered `6`

We are proud to announce our collaboration with Jumeirah Marsa Al Arab (https://www.jumeirah.com/stay/dubai/jumeirah-marsa-al-arab) to enhance their esteemed spaces with our premium pots and planters.

This collaboration underscores our commitment to excellence in quality and service. Marsa Al Arab, recognized for its extraordinariness and sophistication, has selected Pots&Planters to contribute to its atmosphere, aligning with our shared vision of creating stunning green spaces comparable with modern interiors.

Our team is dedicated to ensuring that we provide the most suitable options for every space, enhancing both aesthetic appeal and functionality. Through this project, we aim to bring greenery to stunning indoor areas, offering guests and visitors an exceptional experience immersed in nature.

### 7. Sky Bay Hotel
- **Title:** Meet Pots&Planters at Sky Bay Hotel.
- **Published:** 2025-10-30
- **Suggested slug:** `pots-and-planters-sky-bay-hotel`
- **Category:** Projects & Collaborations
- **Image mapping:** source numbered `7`

We presents our latest project at Sky Bay Hotel, a contemporary hospitality destination located in the heart of Business Bay.

As part of the hotel’s ongoing commitment to modern aesthetics and comfort, our team was invited to enhance the space with elegant greenery solutions. We provided premium planters by Fleur Ami, known for their refined design and exceptional quality, and carried out both live and artificial plants tailored to the hotel’s atmosphere.

Through thoughtful selection of materials and forms, every detail was curated to complement the architectural identity of Sky Bay Hotel, adding a touch of vitality and harmony to its visual landscape.

We continue to collaborate with leading hotels, restaurants, public spaces and private residences across the region, bringing bespoke greenery concepts to life — from design and sourcing to final installation.

## Data model and future Filament integration
Create a clean, typed, frontend-side content source (not inline JSX), with stable `id`, `slug`, `title`, `excerpt`, `body` (structured paragraphs/blocks or safely-rendered rich text), `coverImage`, `coverAlt`, `category`, `publishedAt`, `status` (`published`/`draft`), `seoTitle`, `seoDescription`, optional `ogImage`, optional related entity references and optional external links. Keep source image references and original publication dates. Support `draft` filtering, stable sorting, and future API serialization. Avoid inventing a CMS in frontend. In phase 3 Filament should manage news records, content blocks, media, category, publication and SEO fields; do not implement Filament in this task.

## SEO
- Indexable `/news` and each published `/news/[slug]`, static generation or server rendering as fits existing architecture. Use `generateStaticParams` if appropriate for mock data.
- Unique, accurate title and meta description for listing and each article, canonical URLs using the site's configured production origin, Open Graph/Twitter metadata and cover image.
- Semantic `main`, `article`, `time dateTime`, single H1, proper H2/H3. `NewsArticle`/`Article` JSON-LD only with supported fields: headline, datePublished, image, publisher only if verified by project configuration, canonical URL; no invented author/dateModified.
- Breadcrumbs and existing BreadcrumbList convention if present. Sitemap inclusion should follow existing approach (do not overhaul sitemap infrastructure).
- Excerpts should be concise and accurate to the source, not fabricated claims. `alt` should describe actual image after inspection, not assume it depicts a claimed project if uncertain.
- Avoid keyword stuffing; B2B relevance should come from genuine content, navigation and related category/Solutions links.

## Design and UX
- Match existing restrained editorial style, typography, spacing, olive accents and mobile breakpoints.
- Responsive listing cards (desktop/tablet/mobile), correct image aspect ratios, readable article width and headings, no horizontal overflow.
- Links to `/solutions`, `/shop`, `/contacts` can appear as contextual CTAs without changing historical article text.
- Avoid duplicate Header/Footer or global style changes. Preserve accessible focus and descriptive link labels.

## Acceptance criteria
1. All 7 articles present, with exact dates and no invented articles.
2. Each article has a stable, working `/news/[slug]` route and is reachable from listing.
3. Real local images correctly matched to news 1–7, or missing/ambiguous files explicitly reported.
4. Listing ordered newest-first; historical exhibition and service announcements not represented as current events/guarantees.
5. SEO metadata, semantic server-rendered content, canonical, JSON-LD where valid.
6. Responsive desktop/mobile and accessible links.
7. Existing Header/Footer, Solutions, Enquiry Cart and other approved pages unchanged.
8. TypeScript/lint/build checks run and outcomes reported.
9. Final report: changed files, image mappings, slugs, tests, unresolved factual or asset issues.

## Important limitation
Codex has local filesystem access in the user's development environment; the spec author does not. The image directory path and numbered mapping are supplied by the user but not inspected in this document. Codex must verify actual files before using them.
