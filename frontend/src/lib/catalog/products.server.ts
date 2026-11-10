import 'server-only';
import type { CatalogFacet, CatalogQuery, CatalogResult, Product } from '@/types/catalog';
import { ServerApiError, serverApiFetch } from '@/lib/api.server';

type SpecificationDto = { code: string; label: string; value: string; displayText: string };
type ProductListDto = {
  id: string; slug: string; sku: string; name: string; brand: { id: string; name: string };
  priceAed: string | null; currency: 'AED'; categoryIds: string[];
  primaryCategory: { id: string; slug: string; name: string; path: string };
  canonicalPath: string; cardImage: { url: string; alt: string } | null;
  cardSpecifications: SpecificationDto[]; cardSpecificationsText: string;
};
type ProductDetailDto = ProductListDto & {
  series: { id: string; name: string } | null; descriptionHtml: string | null;
  seo: { title: string; description: string | null; ogImage: string | null; robots: 'noindex,follow' };
  images: { url: string; cardUrl: string | null; thumbUrl: string | null; alt: string; order: number; isMain: boolean }[];
  attributes: NonNullable<Product['attributesDto']>;
};
type FacetDto = CatalogFacet;
type ListResponse = { data: ProductListDto[]; meta: { page: number; perPage: number; total: number; lastPage: number; sort: string }; facets: FacetDto[] };
type DetailResponse = { data: ProductDetailDto };

function fromDto(dto: ProductListDto | ProductDetailDto): Product {
  const detail = 'images' in dto ? dto : undefined;
  return {
    id: dto.id,
    slug: dto.slug,
    name: dto.name,
    sku: dto.sku,
    categoryIds: dto.categoryIds,
    primaryCategoryId: dto.primaryCategory.id,
    primaryCategorySlug: dto.primaryCategory.slug,
    canonicalPath: dto.canonicalPath,
    image: dto.cardImage?.url ?? null,
    imageAlt: dto.cardImage?.alt,
    images: detail?.images.map((image) => ({ src: image.url, alt: image.alt, order: image.order })) ?? (dto.cardImage ? [{ src: dto.cardImage.url, alt: dto.cardImage.alt }] : []),
    cardSpecifications: dto.cardSpecifications,
    cardSpecificationsText: dto.cardSpecificationsText,
    priceAed: dto.priceAed,
    currency: dto.currency,
    published: true,
    featured: false,
    sample: false,
    attributes: {},
    ...(detail ? {
      descriptionHtml: detail.descriptionHtml,
      seo: detail.seo,
      attributesDto: detail.attributes,
      details: Object.fromEntries(detail.attributes.filter((attribute) => ['material', 'usage', 'color', 'size'].includes(attribute.code) && typeof attribute.value === 'string').map((attribute) => [attribute.code, attribute.value as string])),
    } : {}),
  };
}

export function apiQueryString(query: CatalogQuery, categorySlug?: string): string {
  const params = new URLSearchParams({ page: String(query.page), perPage: String(query.perPage), sort: query.sort === 'featured' ? 'newest' : query.sort });
  if (categorySlug) params.set('category', categorySlug);
  for (const [code, filter] of Object.entries(query.filters)) {
    if (Array.isArray(filter)) for (const value of filter) params.append(`filter[${code}][]`, value);
    else { if (filter.min !== undefined) params.set(`filter[${code}][min]`, filter.min); if (filter.max !== undefined) params.set(`filter[${code}][max]`, filter.max); }
  }
  return params.toString();
}

export async function getProducts(query: CatalogQuery, categorySlug?: string): Promise<CatalogResult> {
  const response = await serverApiFetch<ListResponse>(`/v1/products?${apiQueryString(query, categorySlug)}`);
  return {
    products: response.data.map(fromDto),
    total: response.meta.total,
    page: response.meta.page,
    pageCount: Math.max(1, response.meta.lastPage),
    perPage: response.meta.perPage,
    facets: response.facets,
    sorts: [
      { value: 'newest', label: 'Newest' },
      { value: 'name-asc', label: 'Name A–Z' },
      { value: 'name-desc', label: 'Name Z–A' },
      { value: 'price-asc', label: 'Price low–high' },
      { value: 'price-desc', label: 'Price high–low' },
    ],
  };
}

export async function getProductBySlug(slug: string): Promise<Product | undefined> {
  try {
    const response = await serverApiFetch<DetailResponse>(`/v1/products/${encodeURIComponent(slug)}`);
    return fromDto(response.data);
  } catch (error) {
    if (error instanceof ServerApiError && error.status === 404) return undefined;
    throw error;
  }
}

export async function getRelatedProducts(product: Product, limit = 4): Promise<Product[]> {
  if (!product.primaryCategorySlug) return [];
  const response = await getProducts({ categoryId: product.primaryCategoryId, filters: {}, sort: 'newest', page: 1, perPage: Math.min(48, Math.max(1, limit + 1)) }, product.primaryCategorySlug);
  return response.products.filter((candidate) => candidate.id !== product.id).slice(0, Math.max(0, limit));
}
