# FE-01 — Design System & Shared Layout

**Project:** Pots & Planters 2.0  
**Stage:** 2 — Frontend  
**Stack:** Next.js + TypeScript + Mantine + Turbopack (already installed and running)  
**Status:** Implementation specification for Codex

## 1. Goal

Build the responsive visual foundation of the new Pots & Planters website based on the **actual legacy OpenCart theme and desktop/mobile screenshots**. Preserve recognizable premium branding, while modernizing layout, navigation and accessibility. Deliver a reusable Mantine theme and shared application shell, not complete page designs.

## 2. Inputs and inspection — mandatory before coding

- Inspect `references/opencart/` recursively, especially legacy theme templates, CSS, JS, logos, icons and fonts.
- Inspect `references/screenshots/` (or the actual screenshot directory) and identify screenshots of **main, product, category, cart**, in desktop and mobile variants.
- Inspect existing `frontend/` architecture, Next.js App Router, Mantine integration, installed dependencies, and scripts; preserve working Turbopack/HMR.
- If any expected files are missing, report exactly what is missing and proceed with what is available; **do not invent** brand colors, font families, logos, dimensions or screenshot details.
- Before implementing, create `docs/frontend/FE-01-design-inventory.md` with: source file paths; verified colors (hex/rgb), fonts (including licensing/availability), logos, header/footer structures, spacing, breakpoints if discoverable, desktop/mobile navigation behavior, reusable motifs, and unresolved ambiguities. Distinguish observed facts from proposed modernization. Reference screenshots by filename.

## 3. Scope — implement

### 3.1 Design tokens and Mantine

- Centralize verified colors, typography, radius, spacing, shadows, focus states, and responsive breakpoints in the existing Mantine theme setup; do not create competing styling systems.
- Preserve the premium, clean aesthetic: neutral backgrounds, legible dark text, restrained olive accents **only where supported by sources**, generous image-friendly layout.
- Use existing fonts/assets when legally and technically appropriate; ensure fallbacks and avoid shipping unused font files.
- Define reusable responsive page container and spacing patterns, button/link variants and consistent interactive states.
- Avoid global CSS rules that unintentionally override Mantine components.

### 3.2 Shared shell

- Responsive site header: brand logo linking to `/`, primary navigation, accessible search entry, catalogue entry, cart entry, and clear desktop/mobile layouts.
- Mobile menu: open/close, focus management, keyboard support, overlay/scroll handling, links that close the menu. Use Mantine primitives when suitable.
- Shared footer: company information, navigation, contact details and showroom section, organized for desktop and mobile. Use **placeholder content clearly labeled** until stage 3 connects real contacts; do not fabricate addresses/phone numbers.
- Persistent floating actions: **WhatsApp** and **Catalogues**; accessible names, responsive positioning, safe-area padding, no overlap with menu or key controls. Catalogue action links to `/catalogues`. WhatsApp destination is configurable via one centralized mock config, not scattered literals. Do not invent a phone number; if absent, render a visibly disabled/non-navigating state with a development note.
- Search entry: accessible input or trigger integrated into the header; navigation to `/search?q=...` using encoded query; no search backend or result page implementation in FE-01.
- Cart entry: visual shell with mock item count sourced from one mock interface (default zero); no cart logic.
- Provide a reusable layout used by routes via App Router, preserving Mantine provider and server/client boundaries.

### 3.3 Preview and integration

- Existing home placeholder may be replaced with a **minimal shell demonstration** showing header, footer and floating actions; **do not build the homepage**.
- Add clearly temporary placeholders for `/catalogues` and `/search` **only if necessary** to avoid dead navigation during preview; these are not feature implementations.
- Keep shared content configurable through typed constants/interfaces so stage 3 can replace mock contacts with Laravel API without rewriting layout components.
- Use semantic HTML (`header`, `nav`, `main`, `footer`), skip-to-content link, keyboard-visible focus and sensible heading hierarchy.

## 4. Out of scope — strict

Do not implement: homepage sections, category/product/cart pages, product search logic, real API calls, Laravel models or Filament resources, lead forms, Telegram, PDF gating, news, checkout, SEO JSON-LD/sitemap/redirects, new Docker services, or broad dependency upgrades. Do not import legacy OpenCart PHP templates, jQuery plugins, old scripts or styles wholesale into production frontend. Do not modify infrastructure unless an existing configuration prevents FE-01 from working; report first.

## 5. Suggested structure (adapt to existing project)

```text
frontend/
  src/ (or existing app root)
    app/
      layout.tsx
      page.tsx
    components/layout/
      SiteHeader.tsx
      MobileNavigation.tsx
      SiteFooter.tsx
      FloatingActions.tsx
      SearchEntry.tsx
      CartEntry.tsx
    theme/
      index.ts
    config/
      site.ts
    types/
      site.ts

docs/frontend/
  FE-01-design-inventory.md
  FE-01-implementation-report.md
```

These paths are illustrative. Follow existing conventions and avoid duplicate providers/components.

## 6. Work packages

**FE-01.1 — Inventory.** Read actual files/screenshots; document verified design decisions and uncertain items. Do not code until inventory is written.

**FE-01.2 — Theme.** Implement tokens, Mantine theme and global typography/responsive container; validate HMR.

**FE-01.3 — Header.** Desktop navigation, mobile menu, search trigger and cart entry with keyboard accessibility.

**FE-01.4 — Footer and floating actions.** Responsive footer, WhatsApp/Catalogues, safe-area and z-index behavior.

**FE-01.5 — Integration and verification.** Mount shell, run checks, manually inspect responsive states, write implementation report.

Each package should have small, reviewable changes. Stop and report blockers rather than guessing missing business data.

## 7. Acceptance criteria

1. Existing Docker Compose infrastructure, Next.js dev mode and Turbopack HMR remain functional.
2. Existing Laravel, Filament, Redis, Horizon configurations remain untouched.
3. Design inventory references actual files and desktop/mobile screenshots; separates facts from assumptions.
4. Real available logo/brand assets used where verified; no invented company identifiers.
5. Header/footer render consistently on desktop and mobile and are reusable across routes.
6. Mobile menu opens/closes, is keyboard-operable and does not trap background scrolling incorrectly.
7. Search accepts input and constructs a correctly encoded `/search?q=` URL; does not pretend to return real results.
8. Cart indicator shows mock state without implementing cart behavior.
9. `/catalogues` action navigates to the intended route; WhatsApp never points to a fabricated number.
10. Floating controls do not cover essential navigation/form controls at common viewport widths (at minimum 375px, 768px, 1280px); account for mobile safe areas.
11. No accidental horizontal overflow at 375px, 768px and 1280px.
12. Accessible names, focus indicators, semantic landmarks and skip link present.
13. `lint`, `typecheck`, and production build pass **using scripts supported by this repository**; if scripts are missing, document actual equivalent commands and outcomes. Do not claim tests were run if they were not.
14. No legacy JS/PHP runtime dependency, new backend feature or unrequested infrastructure changes.
15. README or implementation report describes file changes, decisions, checks, known limitations and how to preview.

## 8. Codex final report format

Create `docs/frontend/FE-01-implementation-report.md` with:

- Actual source references and extracted design tokens.
- Changed/created files.
- Completed work packages and acceptance checklist (pass/fail/not verified).
- Commands executed and their actual output summaries.
- Screens/viewport sizes manually checked; if no browser available, mark visual checks **not verified**.
- Open questions, especially missing assets and WhatsApp number.
- Confirmation that homepage, catalog, backend and SEO were not implemented.

## 9. Instruction to Codex

> Implement **only FE-01** as specified. Begin with an inventory of the real legacy theme and screenshots. Preserve the working monorepo and infrastructure. Do not invent design facts or business contact data. Implement a reusable, responsive Mantine layout; validate it with available checks and deliver a transparent report. If the source material contradicts the specification, document the conflict and request a decision before making a major design departure.
