export type Category = {
  id: string; slug: string; path: string; name: string; heading: string; seoTitle: string; description: string;
  introHeading?: string | null; introParagraphs?: string[]; image?: string | null; imageAlt?: string | null; ogImage?: string | null; seoDescription?: string; tileDescription?: string;
};
export type Catalogue = {
  id: string; slug: string; title: string; description: string | null; coverImage: string | null; coverAlt?: string | null;
  categoryIds: string[]; isPublished: true;
  categories?: { id: string; name: string; slug: string }[];
};
export const filterKeys = ['material', 'color', 'size', 'usage'] as const;
export type FilterKey = typeof filterKeys[number];
export type CatalogFilters = Record<string, string[] | { min?: string; max?: string }>;
export type ProductSpecifications = {
  diameterCm?: number; heightCm?: number; diameterUnit?: string; heightUnit?: string; finish?: string;
};
export type ProductCardSpecification = { code: string; label: string; value: string; displayText: string };
export type ProductAttribute = { id: string; code: string; label: string; shortLabel: string | null; type: 'text' | 'number' | 'boolean' | 'select' | 'multiselect'; value: string | boolean | string[] | null; unit: string | null; options: { value: string; label: string }[] };
export type ProductImage = { src: string; alt: string; order?: number };
export type Product = {
  id: string; slug: string; name: string; sku: string; categoryIds: string[];
  primaryCategoryId?: string; images?: ProductImage[]; currency?: 'AED';
  description?: string | null; seoTitle?: string | null; seoDescription?: string | null;
  descriptionHeading?: string | null; descriptionParagraphs?: string[];
  /** Only verified details belong here; attributes remain catalogue filter fixtures. */
  details?: { material?: string; usage?: string; color?: string; size?: string };
  priceVerified?: boolean;
  image: string | null; imageAlt?: string; specifications?: ProductSpecifications; cardSpecifications?: ProductCardSpecification[]; cardSpecificationsText?: string; attributes: Partial<Record<FilterKey, string[]>>;
  priceAed: number | string | null; published: boolean; featured: boolean; sample: boolean;
  canonicalPath?: string; primaryCategorySlug?: string; descriptionHtml?: string | null; seo?: { title: string; description: string | null; ogImage: string | null; robots: 'noindex,follow' }; attributesDto?: ProductAttribute[];
};
export type CatalogSort = 'newest' | 'featured' | 'name-asc' | 'name-desc' | 'price-asc' | 'price-desc';
export type CatalogQuery = { categoryId?: string; filters: CatalogFilters; sort: CatalogSort; page: number; perPage: number };
export type CatalogFacetOption = { value: string; label: string; count: number };
export type CatalogFacet = { code: string; label: string; type: ProductAttribute['type']; displayType: string; options?: CatalogFacetOption[]; min?: string | null; max?: string | null };
export type CatalogResult = {
  products: Product[]; total: number; page: number; pageCount: number; perPage: number;
  facets: CatalogFacet[]; sorts: { value: CatalogSort; label: string }[];
};
/** Async boundary: the UI may consume a future HTTP implementation without fixture imports. */
export interface CatalogRepository {
  getCatalogues(): Promise<Catalogue[]>;
  getCataloguesByCategory(categorySlug: string): Promise<Catalogue[]>;
  getProductBySlug(slug: string): Promise<Product | undefined>;
  getRelatedProducts(product: Product, limit?: number): Promise<Product[]>;
  getProducts(query: CatalogQuery): Promise<CatalogResult>;
}
