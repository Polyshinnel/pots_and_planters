import { productPath } from '@/lib/catalog/domain';

export function enquiryWhatsAppUrl(destination: string | null, message: string) {
  if (!destination) return null;
  try {
    const url = new URL(destination);
    if (url.protocol !== 'https:' || url.hostname !== 'wa.me' || !/^\/[1-9]\d{6,14}$/.test(url.pathname)) return null;
    url.searchParams.set('text', message);
    return url.href;
  } catch { return null; }
}

export function productWhatsAppUrl(destination: string | null, product: { name: string; sku: string; slug: string; categoryId?: string }, quantity: number, origin: string) {
  try {
    const productUrl = new URL(productPath(product.slug, product.categoryId), origin);
    return enquiryWhatsAppUrl(destination, `Hello Pots & Planters, I would like to enquire about ${product.name} (Model: ${product.sku}), quantity: ${quantity}. ${productUrl.href}`);
  } catch { return null; }
}
