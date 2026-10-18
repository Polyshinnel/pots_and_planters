import { productPath } from '@/lib/catalog/domain';

export function productWhatsAppUrl(destination: string | null, product: { name: string; sku: string; slug: string; categoryId?: string }, quantity: number, origin: string) {
  if (!destination) return null;
  try {
    const url = new URL(destination);
    if (url.protocol !== 'https:' || url.hostname !== 'wa.me' || !/^\/[1-9]\d{6,14}$/.test(url.pathname)) return null;
    const productUrl = new URL(productPath(product.slug, product.categoryId), origin);
    url.searchParams.set('text', `Hello Pots & Planters, I would like to enquire about ${product.name} (Model: ${product.sku}), quantity: ${quantity}. ${productUrl.href}`);
    return url.href;
  } catch { return null; }
}
