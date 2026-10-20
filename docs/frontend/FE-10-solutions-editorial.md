# FE-10 — Solutions (Editorial concept A)

## Goal
Implement `/solutions` as a polished B2B editorial landing page for Pots & Planters 2.0. This page answers **where and how planters and artificial greenery can be used in a customer's space**, then guides users to relevant product collections and project enquiries. This is **not** a portfolio of completed company projects and **not** a second product catalogue.

## Context and scope
- Frontend: existing Next.js App Router, current design system, responsive Header/Footer and shared navigation (the `Solutions` link already targets `/solutions`).
- Work within the existing frontend and current conventions. Inspect existing components, routes, asset directories, category data, and styles before editing.
- Phase 2: frontend/mock content only. No Laravel API, Filament, Telegram, new CMS, new contact forms or extra dependencies.
- Preserve manually polished design and all working pages, especially Home, Collections, About Us, Contacts, Catalogues, Cart and product pages.

## Visual direction — Editorial (concept A)
- Five generous, alternating image-and-copy sections with ample whitespace, a refined architectural/editorial look, and the existing olive/neutral palette and typography.
- Desktop: alternate image-left/text-right and text-left/image-right; keep image sizes, copy widths and vertical rhythm consistent.
- Mobile: one column, image and copy grouped clearly per solution; comfortable spacing, no horizontal overflow, readable CTAs. Check narrow viewport around iPhone SE.
- Use existing responsive image practices (`next/image` where appropriate), meaningful alt text and appropriate image sizes; avoid large unoptimized images and layout shift.
- Avoid decorative cards, noisy gradients, icons everywhere, fake counters, autoplay animations or oversized page length.
- Make sections distinct through photography and precise use-case copy, not five repetitions of the same generic paragraph.

## Page layout and copy

### 1. Hero
- Eyebrow: `SOLUTIONS FOR EVERY SPACE`
- H1: `Planters & Greenery for Every Space`
- Lead: `From welcoming hotel entrances to private terraces and contemporary workplaces, discover how planters and artificial greenery can shape the character and function of a space.`
- Supporting line: `Explore ideas for your project, then browse suitable collections or contact our team to discuss your requirements.`
- Hero image: use a suitable **existing, licensed project asset** where possible; do not claim it depicts a Pots & Planters installation unless verified.
- Optional compact jump navigation to the five sections if it fits the established visual language. Do not add a sticky secondary header.

### 2. Five editorial sections
Create these sections in this order, with exact stable anchors and purpose-built copy. Treat suggested copy as editorial starting content; adapt minimally for layout, but do not invent product specifications or company capabilities.

**Hospitality & Hotels** — `#hospitality`
- Heading: `Welcoming Spaces, Considered Details`
- Copy: `From hotel entrances and lobbies to terraces and guest areas, planters can frame circulation, introduce greenery and create a more inviting atmosphere. Explore statement pieces, coordinated arrangements and artificial trees suited to the scale of your space.`
- Relevant collections: Outdoor Pots, Indoor Pots, Artificial Plants & Trees.

**Restaurants & Cafés** — `#restaurants`
- Heading: `Define the Dining Experience`
- Copy: `Planters can help define outdoor seating areas, soften transitions and introduce visual rhythm to dining spaces. Explore options for restaurant terraces, café interiors and hospitality environments where form, proportion and finish matter.`
- Relevant collections: Outdoor Pots, Indoor Pots, Artificial Plants & Trees.

**Offices & Commercial Spaces** — `#offices`
- Heading: `Greenery for Productive Spaces`
- Copy: `Reception areas, shared workspaces and meeting zones benefit from considered greenery and carefully chosen planters. Find forms and finishes that complement contemporary interiors, from understated accents to larger focal points.`
- Relevant collections: Indoor Pots, Artificial Plants & Trees.

**Villas & Residential Projects** — `#residential`
- Heading: `A Natural Extension of Home`
- Copy: `Bring structure and character to entrances, courtyards, terraces and interior spaces. Explore planters and artificial greenery that complement a residential design concept, whether the brief calls for a single statement piece or a coordinated selection.`
- Relevant collections: Outdoor Pots, Indoor Pots, Artificial Plants & Trees.

**Retail & Public Spaces** — `#retail`
- Heading: `Make Shared Spaces More Inviting`
- Copy: `In retail environments, galleries and shared public areas, planters can create focal points, soften architectural edges and help define zones. Browse collections with shapes, sizes and finishes that suit the visual language of your space.`
- Relevant collections: Indoor Pots, Outdoor Pots, Artificial Plants & Trees.

Each section should contain:
- A strong editorial image relevant to the environment.
- Section name, headline and one concise descriptive paragraph.
- `Suitable collections` with **working links** to the appropriate existing category routes: `/shop/outdoor-pots`, `/shop/indoor-pots`, `/shop/artificial-plants`.
- A discreet action, e.g. `Explore Collections`, linking to `/shop` or an appropriate existing category. Do not invent filtered URL formats.
- No product grid and no fabricated project/case-study claims.

### 3. Final B2B CTA
- Heading: `Let's Find the Right Fit for Your Project`
- Text: `Tell us about your space, preferred styles and quantities. Our team can help you explore suitable collections and discuss a quotation for your requirements.`
- Primary CTA: `Discuss Your Project` → `/contacts` (reuse existing contact flow).
- Secondary CTA: `Explore Collections` → `/shop`.
- Keep this section concise; do not embed or recreate a form.

## Data and component structure
- Put the five solution entries into one **typed content array/module** (id, section title, headline, copy, image/alt, relevant collection keys/links).
- Render them using one reusable `SolutionSection` (or similarly named) component; alternate visual orientation by index or explicit field.
- Reuse existing category URLs/constants and common UI primitives when available; avoid duplicated content/route definitions.
- Content model should support a later **Gallery (concept B)** layout without rewriting the content.
- Stable semantic IDs must make `/solutions#hospitality`, `#restaurants`, `#offices`, `#residential`, `#retail` work.

## Content and asset integrity
- Inspect `references/opencart/`, `references/screenshots/`, current assets and relevant existing pages for suitable images before choosing.
- Use only assets the project is allowed to publish. If suitable imagery is missing, clearly report the gap; do not silently download unlicensed stock photos or reuse unrelated imagery.
- **Never describe illustrative images as completed Pots & Planters projects.** No fake client names, case studies, project numbers, testimonials, partnerships or technical performance claims.
- Avoid unsupported assertions about outdoor durability, fire ratings, maintenance-free use, delivery time, stock availability or installation services.
- Do not invent product-category-specific filters or URLs.

## SEO and accessibility
- Server-render indexable page content with a single H1, logical H2s and meaningful link labels.
- Set page-specific `metadata.title` and `metadata.description` consistent with existing metadata patterns. Suggested title: `Planter Solutions for Hotels, Offices & Homes | Pots & Planters`.
- Suggested description: `Explore planters and artificial greenery for hotels, restaurants, offices, villas and public spaces. Find suitable collections for your next project.`
- Use correct image alt text, accessible link focus styles and semantic section headings.
- Respect existing navigation and responsive layout; do not alter canonical/robots/sitemap logic outside the scope of this page.

## Acceptance checklist
1. `/solutions` renders without errors on desktop and mobile.
2. Editorial alternating layout is consistent and visually aligned with Home/About Us.
3. All five solution areas appear in the agreed order with correct copy and IDs.
4. Anchor links work directly, including on initial page load.
5. Every `Suitable collections` link goes to a real existing route.
6. Final CTAs navigate to `/contacts` and `/shop`.
7. No fictitious case studies, client references or unsupported product claims.
8. Existing pages, navigation, cart, forms and catalogues are unaffected.
9. No horizontal overflow or awkward image cropping on narrow phones.
10. TypeScript checks, lint (if configured), and production build pass; report commands and results.

## Deliverable
Implement FE-10 in the existing repository, then report: changed files, chosen image sources, any missing assets/content, and test/build results. **Do not implement Gallery concept B yet.** We will review screenshots of desktop and mobile before deciding whether to keep or change the layout.
