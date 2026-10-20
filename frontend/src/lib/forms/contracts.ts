import type { Catalogue } from '@/types/catalog';

export const catalogueRoles = [
  { value: 'interior_designer', label: 'Interior Designer' },
  { value: 'architect', label: 'Architect' },
  { value: 'landscape_designer', label: 'Landscape Designer' },
  { value: 'contractor', label: 'Contractor / Fit-out Company' },
  { value: 'hospitality', label: 'Hotel / Hospitality Representative' },
  { value: 'procurement', label: 'Procurement / Purchasing' },
  { value: 'developer', label: 'Property Developer' },
  { value: 'reseller', label: 'Retail / Reseller' },
  { value: 'private_customer', label: 'Private Customer' },
  { value: 'other', label: 'Other' },
] as const;
export type CatalogueRole = typeof catalogueRoles[number]['value'];
export type ContactValues = { name: string; phone: string; email: string; company: string };
export type LeadValues = ContactValues & { message: string; role: string; roleOther: string };
export type FormErrors = Partial<Record<keyof LeadValues | 'quantity', string>>;
/** Supply a pathname only, without query parameters or fragments containing personal data. */
export type LeadSource = { pagePath?: string; placement?: string };
type ContactPayload = { name: string; phone: string; email: string; company?: string; source?: LeadSource };
export type ContactEnquiryPayload = ContactPayload & { type: 'contact'; message: string };
export type ProductEnquiryPayload = ContactPayload & {
  type: 'product_enquiry'; productId: string; sku: string; quantity: number; message?: string;
};
export type CatalogueDownloadPayload = ContactPayload & {
  type: 'catalogue_download'; catalogueId: string; role: CatalogueRole; roleOther?: string;
};
export type LeadPayload = ContactEnquiryPayload | ProductEnquiryPayload | CatalogueDownloadPayload;
/** FE-06 intentionally has no success result. Extend after durable persistence is implemented. */
export type LeadSubmissionResult = { status: 'unavailable' | 'failed' } | { status: 'persisted'; leadId: string };
export type LeadSubmitAdapter<P extends LeadPayload> = (payload: P) => Promise<LeadSubmissionResult>;
export type LeadFormProps<P extends LeadPayload> = { source?: LeadSource; onSubmit?: LeadSubmitAdapter<P> };
export type ProductEnquiryContext = {
  id: string; sku: string; name: string; image?: string | null;
  specifications?: readonly { label: string; value: string }[];
};
export type CatalogueEnquiryContext = Pick<Catalogue, 'id' | 'title'> & Partial<Pick<Catalogue, 'coverImage' | 'categoryIds'>>;
