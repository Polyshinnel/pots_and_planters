import { categories, products } from '@/data/catalog.mock';
import { catalogues } from '@/data/catalogues.mock';
import type { CatalogRepository } from '@/types/catalog';
import { selectCatalogues } from './catalogues';
import { selectProducts } from './domain';

// Local adapter only. Keep fixture imports out of UI; replace this adapter in stage 3.
export const catalogRepository: CatalogRepository = {
  async getCatalogues() { return selectCatalogues(catalogues); },
  async getCataloguesByCategory(categoryId) { return selectCatalogues(catalogues, categoryId); },
  async getCategories() { return categories; },
  async getCategoryByPath(path) { return categories.find((category) => category.path === path); },
  async getProductBySlug(slug) { return products.find((product) => product.published && product.slug === slug); },
  async getProducts(query) { return selectProducts(products, categories, query); },
};
