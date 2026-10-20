# FE-06 Forms Contract

**Status:** frontend contract only. No lead endpoint, email or Telegram delivery, persistence, or catalogue file release is implemented.

## Components

- `ContactEnquiryForm` is a standalone contact lead form. The homepage's “Discuss Your Project” buttons open it in a responsive modal. It can also be mounted on a future Contacts page.
- `ProductEnquiryForm` is a standalone single-product lead form. `ProductEnquiryDialog` hosts it on the product detail page. Its product ID and SKU come from the current product record; quantity is controlled by the product page and is shared with its quantity selector. It does not write to the enquiry cart.
- `CatalogueDownloadForm` collects a lead for a selected catalogue. `CatalogueDownloadDialog` is the typed modal wrapper for a future catalogue trigger. It validates and retains details, but does not issue a file.

All forms accept an optional `source` (`pagePath`, `placement`) and typed async `onSubmit` adapter. Validation failures retain the draft and focus the first invalid control. Closing dialogs retains the draft while mounted; changing product identity or catalogue ID resets its form state. Dialogs support Escape, overlay closing, focus trapping/restoration, reduced motion, and full-screen layout on narrow screens.

## Shared fields and validation

- Name, phone and email are required in all three forms.
- Company is optional. Its label is “Company / Project name” for contact and product leads, and “Company” for catalogue leads.
- Message is required for contact, optional for product enquiries, and omitted from catalogue leads.
- Email is checked syntactically. International phone values accept common punctuation and 7–15 digits without assuming a country code or region.
- Values are trimmed and nonprinting control characters removed before payload construction. Length limits: name 200, phone 60, email 254, company 200, message 5,000, optional other role 200 characters.
- Product quantity is a whole number from 1 to the shared enquiry limit (999,999). Invalid fields show field-level messages; quantity is not persisted in a second store.
- Client validation improves form use; it does not provide server-side security.

## Catalogue role values

`Who are you?` is required and has one of these stable values:

| Value | Display label |
| --- | --- |
| `interior_designer` | Interior Designer |
| `architect` | Architect |
| `landscape_designer` | Landscape Designer |
| `contractor` | Contractor / Fit-out Company |
| `hospitality` | Hotel / Hospitality Representative |
| `procurement` | Procurement / Purchasing |
| `developer` | Property Developer |
| `reseller` | Retail / Reseller |
| `private_customer` | Private Customer |
| `other` | Other |

Selecting `other` reveals optional `roleOther`. The role qualifies the catalogue lead; it is not account authorization.

## Typed payloads

Every payload has a stable `type` discriminator and trimmed contact values. Empty optional values are omitted. Source context excludes query strings and fragments.

```ts
type ContactEnquiryPayload = ContactFields & {
  type: 'contact'; message: string;
};
type ProductEnquiryPayload = ContactFields & {
  type: 'product_enquiry'; productId: string; sku: string;
  quantity: number; message?: string;
};
type CatalogueDownloadPayload = ContactFields & {
  type: 'catalogue_download'; catalogueId: string;
  role: CatalogueRole; roleOther?: string;
};
type ContactFields = {
  name: string; phone: string; email: string;
  company?: string; source?: { pagePath?: string; placement?: string };
};
```

The product payload contains stable product identifiers and quantity, not cart lines or a client-trusted price. The catalogue payload contains the catalogue ID, not only its display title. No browser-trusted creation timestamp is included.

## Submission boundary and proposed Laravel integration

With no adapter, a valid submission only displays an honest unavailable notice. It sends no data, records no lead, releases no PDF and retains the entered values. There is no fake success or fake network wait. No contact details are written to logs or analytics.

The optional adapter contract is `LeadSubmitAdapter<P> = (payload: P) => Promise<LeadSubmissionResult>`. FE-06 supports unavailable and failed results. A future adapter may return `{ status: 'persisted', leadId }` only after durable server persistence; this lets a real response be distinguished from a mock preview. The catalogue UI still does not auto-download a file.

Proposed phase-3 endpoints, subject to Laravel API design:

- `POST /api/v1/leads` for all three discriminated lead types.
- After durable catalogue-lead persistence and server-side authorization, a separate server-authorized catalogue download response, for example `POST /api/v1/catalogues/{catalogueId}/download` with the persisted lead identifier.

The server must validate the identifiers and fields, persist the lead before reporting it saved, and enforce gated download access. Telegram delivery should be queued after persistence; notification failure must not erase a saved lead. No endpoint is implemented by FE-06.
