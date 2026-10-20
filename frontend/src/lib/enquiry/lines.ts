import { resolveEnquiryProduct } from '@/lib/catalog/repository';
import { productImages, productSpecifications } from '@/lib/catalog/product-detail';
import { productPath } from '@/lib/catalog/domain';
import type { EnquiryItem } from './domain';

/** Display data is resolved from the catalogue, never trusted from browser storage. */
export function resolveEnquiryLine(item: EnquiryItem) {
  const product = resolveEnquiryProduct(item.productId, item.sku);
  // Mock catalogue prices are still useful for the enquiry estimate even though
  // they are unverified; the totals UI clearly labels them as informational.
  const price = product?.priceAed;
  return {
    ...item,
    lineKey: item.sku,
    name: product?.name ?? `Model ${item.sku}`,
    href: product ? productPath(product.slug, product.primaryCategoryId ?? product.categoryIds[0]) : null,
    imageUrl: product ? productImages(product)[0]?.src : undefined,
    attributes: product ? productSpecifications(product) : [],
    unitPriceAed: typeof price === 'number' && Number.isFinite(price) && price >= 0 ? price : null,
    available: Boolean(product),
  };
}
export type EnquiryCartLine = ReturnType<typeof resolveEnquiryLine>;
export function deriveEnquiry(items: readonly EnquiryItem[]) {
  const lines = items.map(resolveEnquiryLine);
  return {
    lines,
    totalQuantity: items.reduce((sum, item) => sum + item.quantity, 0),
    uniqueLineCount: items.length,
    knownPriceSubtotalAed: lines.reduce((sum, line) => sum + (line.unitPriceAed ?? 0) * line.quantity, 0),
    hasUnpricedItems: lines.some((line) => line.unitPriceAed === null),
  };
}
