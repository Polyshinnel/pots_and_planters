import { validQuantity, MAX_ENQUIRY_QUANTITY } from '@/lib/enquiry/domain';
import { catalogueRoles, type ContactValues, type FormErrors, type LeadValues } from './contracts';

export const fieldLimits = { name: 200, phone: 60, email: 254, company: 200, message: 5000, roleOther: 200 } as const;
/** Plain text only: strip nonprinting controls, preserve message line breaks, trim at payload boundaries. */
export const cleanText = (value: string): string => value.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '').trim();
export function validateContactFields(form: ContactValues): FormErrors {
  const errors: FormErrors = {};
  const name = cleanText(form.name);
  if (!name) errors.name = 'Enter your name.';
  else if (name.length > fieldLimits.name) errors.name = 'Use 200 characters or fewer.';
  const phone = cleanText(form.phone);
  const digits = phone.replace(/\D/g, '');
  if (phone.length > fieldLimits.phone || !/^[+\d\s().-]+$/.test(phone) || digits.length < 7 || digits.length > 15 || (phone.match(/\+/g)?.length ?? 0) > 1 || phone.includes('+') && !phone.startsWith('+')) errors.phone = 'Enter a valid international phone number (7–15 digits).';
  const email = cleanText(form.email);
  if (email.length > fieldLimits.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Enter a valid email address.';
  if (cleanText(form.company).length > fieldLimits.company) errors.company = 'Use 200 characters or fewer.';
  return errors;
}
export function validateLead(form: LeadValues, type: 'contact' | 'product_enquiry' | 'catalogue_download', quantity?: string): FormErrors {
  const errors = validateContactFields(form);
  if (type !== 'catalogue_download') {
    if (type === 'contact' && !cleanText(form.message)) errors.message = 'Enter your message.';
    else if (cleanText(form.message).length > fieldLimits.message) errors.message = 'Use 5,000 characters or fewer.';
  }
  if (type === 'product_enquiry' && (!quantity?.trim() || !validQuantity(Number(quantity)))) errors.quantity = `Enter a whole number from 1 to ${MAX_ENQUIRY_QUANTITY.toLocaleString('en-US')}.`;
  if (type === 'catalogue_download') {
    if (!catalogueRoles.some((role) => role.value === form.role)) errors.role = 'Select your role.';
    if (form.role === 'other' && cleanText(form.roleOther).length > fieldLimits.roleOther) errors.roleOther = 'Use 200 characters or fewer.';
  }
  return errors;
}
