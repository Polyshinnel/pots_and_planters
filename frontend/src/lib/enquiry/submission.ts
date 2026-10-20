import { validQuantity, type EnquiryItem } from './domain';
import { resolveEnquiryLine } from './lines';
import { validateContactFields } from '@/lib/forms/validation';

export type EnquiryContactForm = { name: string; phone: string; email: string; company: string; comments: string };
export type EnquiryFormErrors = Partial<Record<keyof EnquiryContactForm | 'cart', string>>;
export type EnquirySubmissionPayload = {
  version: 1;
  contact: EnquiryContactForm;
  items: {
    lineKey: string; productId: string; sku: string; name: string; quantity: number;
    attributes: { label: string; value: string }[];
    informationalUnitPriceAed: number | null;
  }[];
  currency: 'AED';
};
export function validateEnquiryForm(form: EnquiryContactForm): EnquiryFormErrors {
  const errors: EnquiryFormErrors = validateContactFields(form);
  if (form.comments.trim().length > 5000) errors.comments = 'Use 5,000 characters or fewer.';
  return errors;
}
/** Pure snapshot; prices are informational and must be revalidated by Laravel. */
export function buildEnquiryPayload(cart: readonly EnquiryItem[], form: EnquiryContactForm): EnquirySubmissionPayload {
  if (!cart.length || cart.some((item) => !item.productId.trim() || !item.sku.trim() || !validQuantity(item.quantity)) || new Set(cart.map((item) => item.sku)).size !== cart.length) throw new Error('Review the items in your enquiry.');
  if (Object.keys(validateEnquiryForm(form)).length) throw new Error('Check your contact details.');
  return {
    version: 1, currency: 'AED',
    contact: { name: form.name.trim(), phone: form.phone.trim(), email: form.email.trim(), company: form.company.trim(), comments: form.comments.trim() },
    items: cart.map((item) => {
      const line = resolveEnquiryLine(item);
      return { lineKey: line.lineKey, productId: item.productId, sku: item.sku, name: line.name, quantity: item.quantity,
        attributes: line.attributes.map((attribute) => ({ ...attribute })), informationalUnitPriceAed: line.unitPriceAed };
    }),
  };
}
