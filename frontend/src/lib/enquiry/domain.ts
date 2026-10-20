export const ENQUIRY_PATH = '/cart';
export const ENQUIRY_STORAGE_KEY = 'potsandplanters.enquiry.v1';
export const ENQUIRY_STORAGE_VERSION = 1;
/** Input/storage bound, unrelated to stock or minimum order quantities. */
export const MAX_ENQUIRY_QUANTITY = 999999;
export type EnquiryItem = { productId: string; sku: string; quantity: number };
export const validQuantity = (value: unknown): value is number => typeof value === 'number' && Number.isSafeInteger(value) && value >= 1 && value <= MAX_ENQUIRY_QUANTITY;

export function readEnquiry(raw: string | null): EnquiryItem[] {
  if (!raw) return [];
  try {
    const data: unknown = JSON.parse(raw);
    if (!data || typeof data !== 'object' || !('version' in data) || data.version !== ENQUIRY_STORAGE_VERSION || !('items' in data) || !Array.isArray(data.items)) return [];
    const items = new Map<string, EnquiryItem>();
    for (const entry of data.items.slice(0, 1000)) {
      if (!entry || typeof entry !== 'object' || typeof entry.productId !== 'string' || !entry.productId.trim()
        || typeof entry.sku !== 'string' || !entry.sku.trim() || !validQuantity(entry.quantity)) continue;
      const previous = items.get(entry.sku);
      if (previous && previous.productId !== entry.productId) continue;
      const quantity = (previous?.quantity ?? 0) + entry.quantity;
      if (validQuantity(quantity)) items.set(entry.sku, { productId: entry.productId, sku: entry.sku, quantity });
    }
    return [...items.values()];
  } catch { return []; }
}

export function setEnquiryQuantity(items: readonly EnquiryItem[], sku: string, quantity: number): EnquiryItem[] {
  if (!validQuantity(quantity)) throw new Error('Enter a valid whole quantity.');
  return items.map((item) => item.sku === sku ? { ...item, quantity } : item);
}

export function addEnquiryItem(items: readonly EnquiryItem[], item: EnquiryItem): EnquiryItem[] {
  if (!item.productId.trim() || !item.sku.trim() || !validQuantity(item.quantity)) throw new Error('Enter a valid whole quantity.');
  const previous = items.find((entry) => entry.sku === item.sku);
  if (previous && previous.productId !== item.productId) throw new Error('This model has changed. Please reload the page.');
  const quantity = (previous?.quantity ?? 0) + item.quantity;
  if (!validQuantity(quantity)) throw new Error(`The enquiry limit is ${MAX_ENQUIRY_QUANTITY.toLocaleString('en-US')} per model.`);
  return previous ? items.map((entry) => entry.sku === item.sku ? { ...entry, quantity } : entry) : [...items, { ...item }];
}
