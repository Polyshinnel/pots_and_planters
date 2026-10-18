export type Category = {
  id: string; slug: string; path: string; name: string; heading: string; seoTitle: string; description: string;
};
export type Catalogue = {
  id: string; slug: string; title: string; description: string; coverImage: string;
  categoryIds: string[]; isPublished: boolean;
};
export const filterKeys = ['material', 'color', 'size', 'usage'] as const;
export type FilterKey = typeof filterKeys[number];
export type CatalogFilters = Partial<Record<FilterKey, string[]>>;
export type ProductSpecifications = { diameterCm?: number; heightCm?: number; finish?: string };
export type ProductImage = { src: string; alt: string; order?: number };
export type Product = {
  id: string; slug: string; name: string; sku: string; categoryIds: string[];
  primaryCategoryId?: string; images?: ProductImage[]; currency?: 'AED';
  description?: string | null; seoTitle?: string | null; seoDescription?: string | null;
  descriptionHeading?: string | null; descriptionParagraphs?: string[];
  /** Only verified details belong here; attributes remain catalogue filter fixtures. */
  details?: { material?: string; usage?: string; color?: string; size?: string };
  priceVerified?: boolean;
  image: string | null; specifications?: ProductSpecifications; attributes: Partial<Record<FilterKey, string[]>>;
  priceAed: number | null; published: boolean; featured: boolean; sample: boolean;
};
export type CatalogSort = 'featured' | 'name-asc' | 'name-desc' | 'price-asc' | 'price-desc';
export type CatalogQuery = { categoryId?: string; filters: CatalogFilters; sort: CatalogSort; page: number; perPage: number };
export type CatalogFacet = { key: FilterKey; label: string; values: string[] };
export type CatalogResult = {
  products: Product[]; total: number; page: number; pageCount: number; perPage: number;
  facets: CatalogFacet[]; sorts: { value: CatalogSort; label: string }[];
};
/** Async boundary: the UI may consume a future HTTP implementation without fixture imports. */
export interface CatalogRepository {
  getCatalogues(): Promise<Catalogue[]>;
  getCataloguesByCategory(categoryId: string): Promise<Catalogue[]>;
  getCategories(): Promise<Category[]>;
  getCategoryByPath(path: string): Promise<Category | undefined>;
  getProductBySlug(slug: string): Promise<Product | undefined>;
  getRelatedProducts(product: Product, limit?: number): Promise<Product[]>;
  getProducts(query: CatalogQuery): Promise<CatalogResult>;
}
