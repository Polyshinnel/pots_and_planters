'use client';

import { apiFetch } from '@/lib/api';
import { isProductUuid, type EnquiryV2Item } from './store-v2';

export type ResolvedProduct = {
  id: string; slug: string; sku: string; name: string; brand: { id: string; name: string };
  priceAed: string | null; currency: string; categoryIds: string[];
  primaryCategory: { id: string; slug: string; name: string; path: string };
  canonicalPath: string; cardImage: { url: string; alt: string } | null;
  cardSpecifications: { code: string; label: string; value: string; displayText: string }[]; cardSpecificationsText: string;
};
export type ResolvedLine = { item: EnquiryV2Item; status: 'available'; product: ResolvedProduct } | { item: EnquiryV2Item; status: 'unavailable' };
const record = (value: unknown): value is Record<string, unknown> => Boolean(value && typeof value === 'object' && !Array.isArray(value));
function validProduct(value: unknown): value is ResolvedProduct {
  if (!record(value) || !isProductUuid(value.id) || typeof value.slug !== 'string' || typeof value.sku !== 'string' || typeof value.name !== 'string'
    || !record(value.brand) || typeof value.brand.id !== 'string' || typeof value.brand.name !== 'string'
    || !(value.priceAed === null || (typeof value.priceAed === 'string' && /^\d+(?:\.\d+)?$/.test(value.priceAed)))
    || typeof value.currency !== 'string' || !Array.isArray(value.categoryIds) || !record(value.primaryCategory)
    || typeof value.canonicalPath !== 'string' || !(value.cardImage === null || (record(value.cardImage) && typeof value.cardImage.url === 'string' && typeof value.cardImage.alt === 'string'))
    || !Array.isArray(value.cardSpecifications) || typeof value.cardSpecificationsText !== 'string') return false;
  const primaryCategory = value.primaryCategory;
  const cardSpecifications = value.cardSpecifications;
  if (!['id', 'slug', 'sku', 'name', 'brand', 'priceAed', 'currency', 'categoryIds', 'primaryCategory', 'canonicalPath', 'cardImage', 'cardSpecifications', 'cardSpecificationsText'].every((key) => key in value)) return false;
  return value.categoryIds.every((id) => typeof id === 'string')
    && record(primaryCategory) && ['id', 'slug', 'name', 'path'].every((key) => typeof primaryCategory[key] === 'string')
    && cardSpecifications.every((entry) => record(entry) && ['code', 'label', 'value', 'displayText'].every((key) => typeof entry[key] === 'string'));
}
export async function resolveProducts(items: readonly EnquiryV2Item[]): Promise<ResolvedLine[]> {
  if (!items.length) return [];
  if (items.length > 100 || items.some((item) => !isProductUuid(item.productId))) throw new Error('Invalid enquiry items.');
  const ids = items.map((item) => item.productId);
  if (new Set(ids).size !== ids.length) throw new Error('Duplicate enquiry product IDs.');
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 12000);
  try {
    const response = await apiFetch<{ data: unknown }>('/v1/products/resolve', {
      method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }), signal: controller.signal,
    });
    if (!record(response) || !Array.isArray(response.data) || response.data.length !== items.length) throw new Error('Invalid product resolve response.');
    return response.data.map((entry, index) => {
      const item = items[index];
      if (!record(entry) || entry.id !== item.productId) throw new Error('Product resolve response order or identity mismatch.');
      if (entry.status === 'unavailable' && Object.keys(entry).sort().join(',') === 'id,status') return { item, status: 'unavailable' };
      if (entry.status === 'available' && validProduct(entry.product) && entry.product.id === item.productId) return { item, status: 'available', product: entry.product };
      throw new Error('Invalid product resolve item.');
    });
  } finally { clearTimeout(timeout); }
}

export function formatResolvedPrice(value: string | null): string {
  if (value === null) return 'Price on enquiry';
  const [whole, fraction] = value.split('.');
  return `AED ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}${fraction === undefined ? '' : `.${fraction}`}`;
}
