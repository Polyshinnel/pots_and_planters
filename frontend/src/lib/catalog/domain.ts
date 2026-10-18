import { filterKeys, type Category, type Product, type CatalogQuery, type CatalogResult, type CatalogFacet, type CatalogFilters } from '../../types/catalog';

export const PAGE_SIZE = 12;
export const facetLabels = { material: 'Material', color: 'Color', size: 'Size', usage: 'Usage' };
export function categoryProducts(products: Product[], categories: Category[], categoryId?: string) {
  if (categoryId && !categories.some((category) => category.id === categoryId)) return [];
  return [...new Map(products.filter((p) => p.published && (!categoryId || p.categoryIds.includes(categoryId))).map((p) => [p.id, p])).values()];
}
export function getFacets(products: Product[]): CatalogFacet[] {
  return filterKeys.map((key) => ({ key, label: facetLabels[key], values: [...new Set(products.flatMap((p) => p.attributes[key] ?? []))].sort() })).filter((facet) => facet.values.length > 0);
}
export function getSorts(products: Product[]): CatalogResult['sorts'] {
  const sorts: CatalogResult['sorts'] = [{ value: 'featured', label: 'Featured' }, { value: 'name-asc', label: 'Name A–Z' }, { value: 'name-desc', label: 'Name Z–A' }];
  if (products.some((p) => p.priceAed !== null && Number.isFinite(p.priceAed))) sorts.push({ value: 'price-asc', label: 'Price low–high' }, { value: 'price-desc', label: 'Price high–low' });
  return sorts;
}
export function parseQuery(params: URLSearchParams, facets: CatalogFacet[], sorts: CatalogResult['sorts'], categoryId?: string): CatalogQuery {
  const filters: CatalogFilters = {};
  for (const facet of facets) {
    const requested = params.getAll(facet.key).flatMap((value) => value.split(','));
    const values = facet.values.filter((value) => requested.includes(value));
    if (values.length) filters[facet.key] = values;
  }
  const rawPage = params.get('page') ?? '1';
  const page = /^\d+$/.test(rawPage) && Number.isSafeInteger(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1;
  const sort = sorts.find((option) => option.value === params.get('sort'))?.value ?? 'featured';
  return { categoryId, filters, sort, page, perPage: PAGE_SIZE };
}
export function queryString(query: Pick<CatalogQuery, 'filters' | 'sort' | 'page'>): string {
  const params = new URLSearchParams();
  for (const key of filterKeys) {
    const values = [...new Set(query.filters[key] ?? [])].sort();
    if (values.length) params.set(key, values.join(','));
  }
  if (query.sort !== 'featured') params.set('sort', query.sort);
  if (query.page > 1) params.set('page', String(query.page));
  const encoded = params.toString();
  return encoded ? `?${encoded}` : '';
}
export function selectProducts(products: Product[], categories: Category[], query: CatalogQuery): CatalogResult {
  const scoped = categoryProducts(products, categories, query.categoryId);
  const filtered = scoped.filter((p) => filterKeys.every((key) => !query.filters[key]?.length || query.filters[key]!.some((value) => p.attributes[key]?.includes(value))));
  const sorted = [...filtered].sort((a, b) => {
    if (query.sort === 'name-asc' || query.sort === 'name-desc') return a.name.localeCompare(b.name, 'en') * (query.sort === 'name-desc' ? -1 : 1) || a.id.localeCompare(b.id);
    if (query.sort === 'price-asc' || query.sort === 'price-desc') {
      if (a.priceAed === null) return b.priceAed === null ? a.id.localeCompare(b.id) : 1;
      if (b.priceAed === null) return -1;
      return (a.priceAed - b.priceAed) * (query.sort === 'price-desc' ? -1 : 1) || a.id.localeCompare(b.id);
    }
    return Number(b.featured) - Number(a.featured); // stable fixture order for ties
  });
  const perPage = Number.isSafeInteger(query.perPage) && query.perPage > 0 ? query.perPage : PAGE_SIZE;
  const pageCount = Math.max(1, Math.ceil(sorted.length / perPage));
  const page = Math.min(Math.max(1, Number.isSafeInteger(query.page) ? query.page : 1), pageCount);
  return { products: sorted.slice((page - 1) * perPage, page * perPage), total: sorted.length, page, pageCount, perPage, facets: getFacets(scoped), sorts: getSorts(scoped) };
}
export { formatPrice } from '../format-price';
export const productPath = (slug: string) => `/product/${encodeURIComponent(slug)}`;
export const SHOP_PATH = '/shop';
