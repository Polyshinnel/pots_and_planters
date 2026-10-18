import type { Catalogue } from '@/types/catalog';

/** Explicit category relationships, independent of product filters and pagination. */
export function selectCatalogues(catalogues: Catalogue[], categoryId?: string): Catalogue[] {
  return catalogues.filter((catalogue) => catalogue.isPublished &&
    (categoryId === undefined || catalogue.categoryIds.includes(categoryId)));
}

export function cataloguesPath(categorySlug?: string): string {
  return categorySlug === undefined ? '/catalogues' : `/catalogues?${new URLSearchParams({ category: categorySlug })}`;
}
