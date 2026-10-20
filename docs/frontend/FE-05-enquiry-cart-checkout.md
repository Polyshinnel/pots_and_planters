# FE-05 — Enquiry Cart & Checkout

**Project:** Pots & Planters 2.0  
**Stage:** Frontend, after FE-04 Product Detail Page  
**Stack:** Existing Next.js + Mantine + Tabler Icons; existing cart state from FE-04  
**Priority:** P0  
**Status:** Implementation specification for Codex

## 1. Objective

Build a complete, coherent **enquiry cart** user experience for B2B buyers, without online payment. A visitor can add products, inspect and edit the cart from the header, review the full enquiry, enter contact details, and prepare a request for later submission to Laravel.

**Do not pretend that a request has been submitted.** Real persistence, Telegram/email notification, and successful-submission confirmation are reserved for the backend integration stage.

## 2. Non-negotiable constraints

1. Inspect the current repository before editing. Reuse the existing cart implementation introduced in FE-04, current header, layout, design tokens, routes and components; preserve manual changes.
2. There must be **one cart state and one set of cart operations** shared by Product Detail, Header mini-cart and checkout. Do not create separate cart arrays or divergent local copies.
3. Keep the project B2B-first. No payment gateway, registration, shipping address form, tax calculation, payment-method selection, delivery-cost promises, or inventory reservation.
4. Do not change infrastructure, Docker, Laravel models/API, Filament, SEO category copy, or completed homepage/catalogue/product layouts except where integration requires a small targeted adjustment.
5. Use installed Mantine and Tabler Icons. Do not install a new state-management library unless the current architecture genuinely cannot support the requirement; document any proposed dependency before adding it.
6. Reuse existing product data and images. Never invent prices, SKU, availability, lead submissions or server responses.
7. All visible interface copy should be in English, consistent with the current UAE-facing site.

## 3. User journey

1. On a product page, select quantity and use **Add to Enquiry**; the item is added to the shared cart. The same SKU added again increases its quantity instead of creating a duplicate row.
2. The header badge updates immediately to the **sum of item quantities**, not the count of unique SKUs.
3. Clicking the header cart icon opens a **mini-cart** rather than navigating directly to checkout.
4. The mini-cart allows the buyer to inspect items, adjust quantity, remove items, and navigate to **Review Enquiry**.
5. On the full enquiry page, the buyer can make the same changes and fill in contact details.
6. Without a connected backend, the form validates and prepares a typed request payload, but **does not report successful submission, clear the cart, or claim Telegram/email notification**.

## 4. FE-05.1 — Audit and normalize shared cart state

Before implementation, document the existing FE-04 cart contract and preserve compatible behavior. Prefer stable identifiers based on SKU **only if SKU uniquely identifies a purchasable variant**; otherwise use a stable variant/line key and keep SKU as a field.

Expected logical line shape (adapt to existing code rather than introducing redundant fields):

```ts
type EnquiryCartLine = {
  lineKey: string;        // stable, unique purchasable configuration
  productId: string;
  sku: string;
  name: string;
  slug: string;
  imageUrl?: string;
  attributes?: string;   // short display summary, when available
  quantity: number;      // integer >= 1
  unitPriceAed: number | null; // null = Price on enquiry
};
```

Required operations: `addItem`, `setQuantity`, `increment`, `decrement`, `removeItem`, `clearCart` (available but **not** called by mock submission), derived `totalQuantity`, `uniqueLineCount`, `knownPriceSubtotalAed`, `hasUnpricedItems`.

- Validate/clamp quantity at state boundaries: positive, finite, integer, with a documented sensible maximum to prevent overflow/abuse.
- `decrement` at quantity 1 must **not remove** the item. A separate Remove action handles deletion.
- Persist to the existing browser storage mechanism (e.g. localStorage); do not replace a working implementation needlessly.
- Avoid Next.js SSR hydration mismatch: use safe client-side hydration and do not render misleading badge counts before state is loaded.
- Define an explicit cart storage version and a graceful strategy for malformed or outdated persisted data; do not crash the page.
- Keep quantity updates synchronous across all mounted cart surfaces.

## 5. FE-05.2 — Header mini-cart

### Desktop

- Clicking the cart icon toggles a Mantine **Popover** anchored to the icon; do **not** navigate on the icon click.
- Clicking outside, pressing Escape, or choosing Review Enquiry closes the Popover.
- Show heading `Your Enquiry` and total unit count.
- Each line shows: thumbnail, product name linked to its page, SKU, compact specifications if present, quantity stepper (`−`, count, `+`), unit price or `Price on enquiry`, and a distinct remove control.
- Show a scrollable items area if the cart contains many lines; header/footer controls remain usable.
- Main action: `Review Enquiry` -> the project's established checkout/enquiry route. Inspect existing routes before choosing; avoid inventing a conflicting `/checkout` vs `/enquiry` path.
- The Popover should be keyboard-accessible with appropriate focus behavior.

### Mobile

- Clicking the header cart icon opens a Mantine **Drawer** instead of a narrow Popover.
- Show the same line-item information and operations, with comfortably tappable controls and safe-area spacing.
- The drawer must scroll correctly and not obscure its close button or primary action.

### Empty state

- Text: `Your enquiry is empty`.
- CTA: `Explore Products` -> `/shop`.
- No misleading checkout CTA for an empty cart.

### Optional interaction

If it fits the existing UX without regressions, show a lightweight confirmation after Add to Enquiry; **do not require** automatically opening the mini-cart. Never open it merely because saved cart state was restored.

## 6. FE-05.3 — Quantity and removal behavior

The mini-cart (desktop/mobile) and full enquiry page must support identical operations:

- `+` increases quantity by one.
- `−` decreases quantity by one, disabled at 1.
- Display the current quantity as an integer. If a direct numeric input is used, reject/normalize empty, zero, negative, fractional, NaN and oversized values; a stepper-only control is acceptable and simpler.
- Remove button deletes the selected line explicitly; give it an accessible label such as `Remove Coral Planter`.
- Changes immediately update the header badge, line subtotal, known-price subtotal and other cart surfaces.
- After the final line is removed, show the empty state without an error or stale checkout form.
- Quantity is preserved after refresh and navigation.
- A repeated add of the same purchasable SKU/variant increments its existing line.

## 7. FE-05.4 — Full Enquiry Checkout page

Reuse the route already present in the project if appropriate. If none exists, choose one canonical route, document it, and update all CTA links consistently.

### Layout

- Page title: `Review Your Enquiry` or `Send an Enquiry` (choose one consistent with current copy).
- On desktop: clear order review and contact form; use a two-column layout if it suits existing design.
- On mobile: single-column layout with product review first, then contact form, then final action.
- List each item with image, name, SKU, available short specifications, quantity stepper, remove button and price display.
- Keep prices in AED and preserve `Price on enquiry` where no numeric price exists.
- Do not introduce payment or delivery address UI.

### Totals

- If all lines have prices, a subtotal of line prices may be shown, explicitly **not a final confirmed quote**.
- If any line is unpriced, label the numeric sum `Known-price subtotal` and add a note that some items are priced on enquiry; **never** display the sum as the total cost of the whole request.
- No invented VAT, shipping fees or discounts.

### Form fields

- `Name` — required.
- `Phone` — required; accept international formats, do not hard-code UAE-only phone validation.
- `Email` — required; validate format.
- `Company / Project name` — optional.
- `Comments` — optional multiline.

Use Mantine form controls and accessible labels, field-level error messages, suitable autocomplete attributes and a privacy-aware brief consent/explanation if one already exists in the project. Do not invent a legal privacy-policy URL.

### Submission boundary (critical)

- FE-05 **must not make a fake successful request** or display `Your enquiry has been sent` when there is no backend.
- Build a typed `EnquirySubmissionPayload` and a pure `buildEnquiryPayload(cart, form)` function.
- Snapshot item identifiers, SKU, product name, chosen quantity and relevant selected attributes for future server submission. Numeric prices, if included, are **informational only**; backend must recalculate/validate them from its own data later.
- Validate that the cart is nonempty and required fields are valid.
- Until a real API exists, final action may be explicitly disabled with `Enquiry submission coming soon`, or open a clearly labeled **development-only payload preview** on local development builds. It must not suggest the enquiry was delivered.
- Preserve cart and form state on validation errors. Do not clear cart on preview.
- Document the future success contract: only after Laravel confirms durable DB persistence may the UI show success and clear the cart. Telegram notification is asynchronous and must not determine whether a saved request is considered successful.
- Do not send contact details to console logs, analytics or external services as a substitute for backend integration.

## 8. FE-05.5 — Integration with product actions

- Confirm `Add to Enquiry` and `Request a Quote` from FE-04 lead to the same cart/enquiry flow; do not create a second independent quote mechanism.
- Preserve selected product quantity when adding to the cart.
- If `Request a Quote` should navigate, add/update the item first, then open the full enquiry page; avoid navigating before state persists.
- Product details and price shown in cart must be consistent with current product mock data; handle missing images and prices gracefully.
- Product links in the mini-cart and checkout should work and preserve standard navigation semantics.

## 9. FE-05.6 — Responsive behavior and accessibility

- Verify at approximately 320, 375, 390, 768, 1024 and 1440 px widths.
- No horizontal scrolling, clipped controls, or collision with floating WhatsApp/Catalogues buttons.
- Keyboard: Enter/Space for actions, Escape to close overlays, sensible focus management.
- Icon-only controls have accessible names; disabled decrement state is conveyed correctly.
- Do not shrink typography to fit excessive data. Long product names wrap safely.
- Respect reduced-motion preferences where transitions are present.

## 10. FE-05.7 — Testing and acceptance

Implement or extend tests using the project's existing test tooling. At minimum verify these scenarios manually if browser automation is unavailable, and state which checks were not run:

1. Header icon opens mini-cart instead of redirecting.
2. Desktop Popover and mobile Drawer render the same cart state.
3. Empty cart shows the empty-state CTA.
4. Add SKU A quantity 2, add SKU A quantity 3 => one line with quantity 5.
5. Header badge counts 5 units, not one line.
6. Increment and decrement work from mini-cart and checkout; decrement at 1 does not remove.
7. Remove deletes the line and updates all views.
8. Quantities survive refresh and navigation.
9. Corrupted persisted cart does not crash the app.
10. Items with known and unknown prices show truthful subtotal labels.
11. Checkout form validates required fields and email format.
12. Nonempty valid form creates the expected typed payload, without fake submission.
13. Cart is not cleared by validation errors or payload preview.
14. Product actions use the shared cart and preserve quantity.
15. Mobile Drawer, form and quantity controls are usable at 320–375 px.
16. Existing homepage, catalogue, product page, filters and floating actions still work.
17. TypeScript checks and production build pass.

## 11. Required deliverables

- Working header mini-cart: desktop Popover + mobile Drawer.
- Shared, persistent cart state and accessible quantity controls.
- Full responsive Enquiry Checkout page and validated form.
- Typed submission payload contract, without fake API success.
- `docs/frontend/FE-05-cart-contract.md` — actual types, operations, storage behavior, chosen route and future backend payload.
- `docs/frontend/FE-05-implementation-report.md` — changed files, verification commands/results, manual checks, known limitations.

## 12. Suggested execution order

1. **FE-05.1** Audit and normalize shared cart state.
2. **FE-05.2** Implement header mini-cart Popover/Drawer.
3. **FE-05.3** Implement quantity and removal behavior everywhere.
4. **FE-05.4** Build full Enquiry Checkout and validated form.
5. **FE-05.5** Integrate Product Detail actions and typed payload.
6. **FE-05.6** Responsive/accessibility pass.
7. **FE-05.7** Run tests, production build, write reports.

## 13. Explicitly out of scope

- Laravel API, database persistence, Filament, Telegram, email delivery.
- Fake order success/thank-you pages.
- Payment, shipping checkout, tax/VAT calculations, stock allocation.
- SEO/microdata work, product copy generation, search functionality.
- Redesign of completed homepage, catalogue, or product detail pages.

**Definition of done:** a buyer can build and edit an enquiry from any product page, manage it directly in the header on desktop or mobile, review it on a dedicated page, and prepare a validated payload. The UI remains honest that the enquiry cannot yet be sent until Laravel integration.
