# FE-06 — B2B Forms Foundation

## Context
Pots & Planters 2.0. Frontend: Next.js + Mantine, current design system already approved. Laravel 13 API, Filament, queues and Telegram integration belong to phase 3. FE-05 enquiry cart/checkout is complete. This task is frontend-only and must preserve existing manual edits and functioning checkout.

## Objective
Build reusable, accessible B2B lead form primitives and three independently composed forms:
1. Contact enquiry
2. Product enquiry
3. PDF catalogue download lead form

Forms should be easy to mount on the forthcoming Contacts and Catalogues pages and on the existing product page. Do not build a giant schema-driven universal form, CRM, admin, API endpoint or mock success service.

## First: inspect the repository
- Read existing Mantine theme, validation approach, form components, FE-05 cart contracts, product page and current mock-data conventions.
- Reuse existing libraries and UI primitives. Avoid dependencies unless genuinely necessary.
- Identify actual paths before editing; don't invent parallel infrastructure.
- Keep this task isolated from Contacts, About Us, Solutions and full Catalogues page implementation.

## Shared form design
- Consistent Mantine TextInput, Textarea, Select, Checkbox (if needed), button, required-field marks, error display, loading/disabled states, mobile layout and accessible labels.
- Common fields: name (required), email (required), phone (required), message (optional except where explicitly required). Optional company/project field where useful; no unnecessary mandatory fields.
- Email syntactic validation, trimmed names/messages, sensible phone input validation without assuming UAE-only numbers, length caps and sanitization at boundaries. Do not confuse client-side validation with server-side security.
- Use shared field components / helpers and shared type contracts; form-specific composition remains separate.
- Typed payloads with stable `type` discriminator: `contact`, `product_enquiry`, `catalogue_download`; ISO timestamps may be generated on submission if needed, but avoid embedding frontend-trusted audit fields in backend contract.
- Keep contact data minimal; no marketing consent by default. If a privacy notice exists in the repo, link it; do not invent legal text or claim compliance.
- Do not log personal contact data to console or analytics.

## Form A — ContactEnquiryForm
Placement: standalone reusable component for forthcoming Contacts page and generic CTA.
Fields: full name*, phone*, email*, company/project (optional), message*.
Payload: type, contact fields, optional company/project, message, optional source/page context.
Design: clean B2B form, clear `Send Enquiry` action.

## Form B — ProductEnquiryForm
Placement: existing product detail page, triggered by `Request a Quote` (or existing equivalent) without breaking `Add to Enquiry` and cart flow.
- Show read-only product context: image where appropriate, title, SKU, selected variant/finish/size if actually present, and quantity (editable integer >=1).
- Fields: name*, phone*, email*, company/project (optional), message (optional).
- Typed payload includes immutable product ID/SKU from current product model, quantity, and selected real variant identifiers if applicable. Never rely only on product title.
- Reuse established quantity semantics; no second product/cart state store. Product enquiry is a single-product lead, not a forced checkout; clarify UX if the existing `Request a Quote` currently routes to checkout: preserve a consistent, explicit distinction between quick product quote and multi-item enquiry cart.
- Do not make claims about stock, pricing or delivery not present in data.

## Form C — CatalogueDownloadForm
Placement: reusable modal/drawer for forthcoming `/catalogues` page; implement form and integration interface now, not full catalogue page.
- Read-only catalogue context: ID, name, optional cover, category; selected from typed mock catalogue data or props.
- Fields: full name*, email*, phone*, **Who are you?***, company (optional).
- `Who are you?` is a required single-select with user-friendly English labels and stable machine-readable values:
  - Interior Designer (`interior_designer`)
  - Architect (`architect`)
  - Landscape Designer (`landscape_designer`)
  - Contractor / Fit-out Company (`contractor`)
  - Hotel / Hospitality Representative (`hospitality`)
  - Procurement / Purchasing (`procurement`)
  - Property Developer (`developer`)
  - Retail / Reseller (`reseller`)
  - Private Customer (`private_customer`)
  - Other (`other`)
- For Other, show optional free-text `roleOther` (consider requiring it only if needed; keep friction low).
- Typed payload includes catalogue ID (not only name), role enum and optional other text, contact details, source/page context.
- After backend integration, the intended flow is: validate -> persist download lead in Laravel -> authorize/issue PDF download -> queue Telegram notification. Telegram failure must never erase the saved lead.
- **In FE-06 mock mode do not claim the lead was registered, do not auto-download gated PDF, and do not show fake success.** Provide an honest unavailable/demo state after validation, with data retained for retry. Do not include a public direct-download bypass if catalogue downloads are to be gated.
- Provide a clear, typed callback/adapter interface for later API integration. Avoid prematurely implementing security in the browser; server will enforce download gating.

## UX behavior and accessibility
- Desktop dialogs should fit comfortably, mobile use responsive Mantine Modal or Drawer where consistent with existing site conventions; keyboard operable, focus managed, Esc and overlay closing, clear error messages.
- Prevent accidental repeated submits; show in-progress state only for actual async action. Without backend, don't simulate a successful network request.
- Form input state must survive a validation error. On backend failure in phase 3 it should remain available for retry.
- When modal closes, follow existing site conventions for resetting or retaining draft; document the choice. Switching to a different product/catalogue must not silently submit stale IDs.
- Clear button labels: `Send Enquiry`, `Request a Quote`, `Get Catalogue` or `Download Catalogue` according to context. Keep English copy consistent with current frontend.
- Responsive and visual QA at desktop and narrow mobile widths.

## Implementation boundaries
- Frontend only. No Laravel API, migrations, Filament resources, Horizon/Telegram, external email service, captcha, fake HTTP success, hardcoded PDF release, new pages for Contacts/About/Solutions.
- Do not refactor unrelated FE-01..FE-05 components or alter user-customized design.
- If checkout has reusable field validation, reuse helpers without changing checkout behavior.
- Provide mock catalogue/product fixtures only to the extent required for component demos/integration. Avoid inventing factual company/catalogue content in production-facing UI.

## Delivery plan
1. Inspect current repo and report integration points.
2. Add shared types, validation and field primitives.
3. Build ContactEnquiryForm.
4. Build ProductEnquiryForm and integrate existing product `Request a Quote` trigger.
5. Build CatalogueDownloadForm and its typed trigger/adapter for FE-07.
6. Implement honest mock submission state and accessible error handling.
7. Verify responsive UI, typecheck, lint (if configured), production build, and regression of FE-05 cart.

## Acceptance criteria
- [ ] All three forms exist as reusable, independently composed components.
- [ ] Shared validation, input styles and payload types are consistent.
- [ ] Required inputs, email, phone, whitespace and invalid quantities are validated.
- [ ] Product form carries correct SKU/product ID and quantity, with no cart regression.
- [ ] `Request a Quote` on product page opens/uses product enquiry intentionally.
- [ ] Catalogue form carries correct catalogue ID and required role enum.
- [ ] Role options cover architects, interior/landscape designers, contractors, hospitality, procurement, developers, resellers, private buyers and other.
- [ ] Forms work with keyboard and narrow mobile screens.
- [ ] Invalid submissions preserve entered values and display field-level errors.
- [ ] No fake success or PDF release occurs without backend persistence.
- [ ] No personal data is logged to console.
- [ ] FE-05 cart/checkout and header mini-cart still work.
- [ ] TypeScript, configured lint and production build pass (or blockers are reported precisely).
- [ ] Developer notes document component props, payload types, mock-mode behavior and future Laravel API contract.

## Deliverables
- Code changes in existing frontend conventions.
- `docs/frontend/FE-06-forms-contract.md`: types, field requirements, role enum, submission semantics, future endpoints as *proposed* only (not implemented).
- `docs/frontend/FE-06-implementation-report.md`: files changed, design decisions, checks run, remaining phase-3 dependencies.

## Important architectural note
Forms collect **leads**, not online orders. The product quote and cart enquiry are distinct UX entry points, but should share field/validation utilities and converge on backend persistence and Telegram notification later. A role value collected for PDF download is lead qualification, not an authorization or user account role.
