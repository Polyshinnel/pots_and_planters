import type { Category, Product, ProductImage } from '@/types/catalog';
import { formatSpecifications } from './format-specifications';

export function primaryCategory(product: Product, categories: Category[]) {
  return categories.find((category) => category.id === product.primaryCategoryId && product.categoryIds.includes(category.id))
    ?? product.categoryIds.map((id) => categories.find((category) => category.id === id)).find(Boolean);
}

export function productImages(product: Product): ProductImage[] {
  // An explicit empty array means no images; legacy image is a compatibility fallback.
  const images = product.images ?? (product.image ? [{ src: product.image, alt: product.name }] : []);
  return [...new Map([...images].filter((image) => image.src.trim()).sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
    .map((image) => [image.src, { ...image, alt: image.alt.trim() || product.name }])).values()];
}

export function productSpecifications(product: Product): { label: string; value: string }[] {
  const rows = [
    { label: 'Dimensions', value: formatSpecifications({ ...product.specifications, finish: undefined }) },
    { label: 'Finish', value: product.specifications?.finish },
    { label: 'Material', value: product.details?.material },
    { label: 'Usage', value: product.details?.usage },
    { label: 'Color', value: product.details?.color },
    { label: 'Size', value: product.details?.size },
  ];
  return rows.flatMap(({ label, value }) => value?.trim() ? [{ label, value: value.trim() }] : []);
}

export function selectRelatedProducts(products: Product[], product: Product, limit = 4) {
  const primary = product.primaryCategoryId ?? product.categoryIds[0];
  const related = products.filter((candidate) => candidate.published && candidate.id !== product.id && candidate.sku !== product.sku
    && candidate.categoryIds.some((id) => product.categoryIds.includes(id)));
  related.sort((a, b) => Number(b.categoryIds.includes(primary)) - Number(a.categoryIds.includes(primary)));
  return [...new Map(related.map((candidate) => [candidate.sku, candidate])).values()].slice(0, Math.max(0, limit));
}
