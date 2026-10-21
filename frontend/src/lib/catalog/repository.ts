import { products } from '@/data/catalog.mock';
import type { CatalogRepository } from '@/types/catalog';
import { selectProducts } from './domain';
import { selectRelatedProducts } from './product-detail';

// Local adapter only. Keep fixture imports out of UI; replace this adapter in stage 3.
export function resolveEnquiryProduct(productId: string, sku: string) {
  return products.find((product) => product.published && product.id === productId && product.sku === sku);
}

export const catalogRepository: CatalogRepository = {
  async getCatalogues() {
    return (await import('./catalogues.server')).getCatalogues();
  },
  async getCataloguesByCategory(categorySlug) {
    return (await import('./catalogues.server')).getCataloguesByCategory(categorySlug);
  },
  async getProductBySlug(slug) { return products.find((product) => product.published && product.slug === slug); },
  async getRelatedProducts(product, limit = 4) { return selectRelatedProducts(products, product, limit); },
  async getProducts(query) { return selectProducts(products, query); },
};
