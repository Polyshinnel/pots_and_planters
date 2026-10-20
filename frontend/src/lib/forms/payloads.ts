import type { CatalogueDownloadPayload, CatalogueEnquiryContext, CatalogueRole, ContactEnquiryPayload, LeadSource, LeadValues, ProductEnquiryContext, ProductEnquiryPayload } from './contracts';
import { cleanText, validateLead } from './validation';

function contact(form: LeadValues, source?: LeadSource) {
  const company = cleanText(form.company);
  const pagePath = source?.pagePath?.split(/[?#]/)[0];
  if (pagePath && (!pagePath.startsWith('/') || pagePath.startsWith('//') || pagePath.length > 2048)) throw new Error('Invalid source pathname.');
  const placement = source?.placement ? cleanText(source.placement) : undefined;
  if (placement && placement.length > 200) throw new Error('Invalid source placement.');
  return { name: cleanText(form.name), phone: cleanText(form.phone), email: cleanText(form.email),
    ...(company ? { company } : {}), ...(pagePath || placement ? { source: { ...(pagePath ? { pagePath } : {}), ...(placement ? { placement } : {}) } } : {}) };
}
function assertValid(form: LeadValues, type: 'contact' | 'product_enquiry' | 'catalogue_download', quantity?: string) {
  if (Object.keys(validateLead(form, type, quantity)).length) throw new Error('Check your details.');
}
export function buildContactPayload(form: LeadValues, source?: LeadSource): ContactEnquiryPayload {
  assertValid(form, 'contact');
  return { type: 'contact', ...contact(form, source), message: cleanText(form.message) };
}
export function buildProductPayload(form: LeadValues, product: ProductEnquiryContext, quantity: string, source?: LeadSource): ProductEnquiryPayload {
  assertValid(form, 'product_enquiry', quantity);
  if (!product.id.trim() || !product.sku.trim()) throw new Error('Product context is unavailable.');
  const message = cleanText(form.message);
  return { type: 'product_enquiry', ...contact(form, source), productId: product.id, sku: product.sku, quantity: Number(quantity), ...(message ? { message } : {}) };
}
export function buildCataloguePayload(form: LeadValues, catalogue: CatalogueEnquiryContext, source?: LeadSource): CatalogueDownloadPayload {
  assertValid(form, 'catalogue_download');
  if (!catalogue.id.trim()) throw new Error('Catalogue context is unavailable.');
  const roleOther = form.role === 'other' ? cleanText(form.roleOther) : '';
  return { type: 'catalogue_download', ...contact(form, source), catalogueId: catalogue.id, role: form.role as CatalogueRole, ...(roleOther ? { roleOther } : {}) };
}
