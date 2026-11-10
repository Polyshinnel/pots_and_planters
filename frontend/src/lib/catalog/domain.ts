import { filterKeys, type Product, type CatalogQuery, type CatalogResult, type CatalogFacet, type CatalogFilters } from '../../types/catalog';

export const PAGE_SIZE = 12;
/** BE-03 stable IDs deliberately match the existing mock product category IDs. */
export const mockProductCategoryIdByPublicId: Record<string, string> = { outdoor: 'outdoor', indoor: 'indoor', artificial: 'artificial' };
export const categorySlugByPublicId: Record<string, string> = { outdoor: 'outdoor-pots', indoor: 'indoor-pots', artificial: 'artificial-plants' };
export const legacyFilterKeys = ['material', 'color', 'size', 'usage'] as const;
export function categoryProducts(products: Product[], categoryId?: string) {
  const mockCategoryId = categoryId ? mockProductCategoryIdByPublicId[categoryId] : undefined;
  if (categoryId && !mockCategoryId) return [];
  return [...new Map(products.filter((p) => p.published && (!mockCategoryId || p.categoryIds.includes(mockCategoryId))).map((p) => [p.id, p])).values()];
}
export function getFacets(_products: Product[]): CatalogFacet[] { return []; }
export function getSorts(products: Product[]): CatalogResult['sorts'] {
  const sorts: CatalogResult['sorts'] = [{ value: 'featured', label: 'Featured' }, { value: 'name-asc', label: 'Name A–Z' }, { value: 'name-desc', label: 'Name Z–A' }];
  if (products.some((p) => p.priceAed !== null && Number.isFinite(p.priceAed))) sorts.push({ value: 'price-asc', label: 'Price low–high' }, { value: 'price-desc', label: 'Price high–low' });
  return sorts;
}
export function parseQuery(params: URLSearchParams, facets: CatalogFacet[], sorts: CatalogResult['sorts'], categoryId?: string): CatalogQuery {
  const filters = parseFilters(params, facets);
  const rawPage = params.get('page') ?? '1';
  const page = /^\d+$/.test(rawPage) && Number.isSafeInteger(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1;
  const sort = sorts.find((option) => option.value === params.get('sort'))?.value ?? 'newest';
  return { categoryId, filters, sort, page, perPage: PAGE_SIZE };
}
export function parseFilters(params: URLSearchParams, facets: CatalogFacet[]): CatalogFilters {
  const filters: CatalogFilters = {};
  for (const facet of facets) {
    const key = `filter[${facet.code}]`;
    if (facet.type === 'number') {
      const min = params.get(`${key}[min]`); const max = params.get(`${key}[max]`);
      if (min !== null || max !== null) filters[facet.code] = { ...(min !== null ? { min } : {}), ...(max !== null ? { max } : {}) };
    } else {
      const values = params.getAll(`${key}[]`);
      const single = params.get(`${key}`);
      if (values.length || single !== null) filters[facet.code] = [...new Set([...values, ...(single === null ? [] : [single])])];
    }
  }
  return filters;
}
export function parseFilterParams(params: URLSearchParams): { filters: CatalogFilters; invalid: boolean } {
  const filters: CatalogFilters = {}; let invalid = false;
  for (const [name, value] of params) {
    const match = /^filter\[([a-z0-9_]+)\](?:\[\]|\[(min|max)\])?$/.exec(name);
    if (!name.startsWith('filter')) continue;
    if (!match || value === '') { invalid = true; continue; }
    const [, code, bound] = match;
    if (bound) {
      const existing = filters[code]; const range = Array.isArray(existing) ? {} : existing ?? {};
      filters[code] = { ...range, [bound]: value };
    } else {
      const existing = filters[code]; const values = Array.isArray(existing) ? existing : existing ? [] : [];
      filters[code] = [...values, value];
    }
  }
  for (const filter of Object.values(filters)) {
    if (!Array.isArray(filter)) {
      for (const value of [filter.min, filter.max]) if (value !== undefined && !/^-?(?:0|[1-9]\d*)(?:\.\d{1,6})?$/.test(value)) invalid = true;
      if (filter.min !== undefined && filter.max !== undefined && compareDecimal(filter.min, filter.max) > 0) invalid = true;
    }
  }
  return { filters, invalid };
}
function compareDecimal(left: string, right: string): number {
  const parts = (value: string) => { const negative = value.startsWith('-'); const [whole, fraction = ''] = value.replace(/^-/, '').split('.'); return { negative, whole: whole.replace(/^0+(?=\d)/, ''), fraction: fraction.replace(/0+$/, '') }; };
  const a = parts(left); const b = parts(right);
  if (a.negative !== b.negative) return a.negative ? -1 : 1;
  const whole = a.whole.length - b.whole.length || a.whole.localeCompare(b.whole);
  const frac = a.fraction.padEnd(Math.max(a.fraction.length, b.fraction.length), '0').localeCompare(b.fraction.padEnd(Math.max(a.fraction.length, b.fraction.length), '0'));
  return (whole || frac) * (a.negative ? -1 : 1);
}
export function queryString(query: Pick<CatalogQuery, 'filters' | 'sort' | 'page'>, includeDefaultSort = false, includeDefaultPage = false, preserve?: URLSearchParams): string {
  const params = new URLSearchParams();
  if (preserve) for (const [key, value] of preserve) if (!key.startsWith('filter[') && !['page', 'sort', 'perPage', ...legacyFilterKeys].includes(key)) params.append(key, value);
  for (const [key, filter] of Object.entries(query.filters)) {
    if (Array.isArray(filter)) for (const value of [...new Set(filter)]) params.append(`filter[${key}][]`, value);
    else { if (filter.min !== undefined) params.set(`filter[${key}][min]`, filter.min); if (filter.max !== undefined) params.set(`filter[${key}][max]`, filter.max); }
  }
  if (query.sort !== 'featured' && (query.sort !== 'newest' || includeDefaultSort)) params.set('sort', query.sort);
  if (query.page > 1 || includeDefaultPage) params.set('page', String(query.page));
  const encoded = params.toString();
  return encoded ? `?${encoded}` : '';
}
export function selectProducts(products: Product[], query: CatalogQuery): CatalogResult {
  const scoped = categoryProducts(products, query.categoryId);
  const filtered = scoped.filter((product) => Object.entries(query.filters).every(([key, filter]) => Array.isArray(filter) && filter.length ? filter.some((value) => product.attributes[key as keyof Product['attributes']]?.includes(value)) : true));
  const sorted = [...filtered].sort((a, b) => {
    if (query.sort === 'name-asc' || query.sort === 'name-desc') return a.name.localeCompare(b.name, 'en') * (query.sort === 'name-desc' ? -1 : 1) || a.id.localeCompare(b.id);
    if (query.sort === 'price-asc' || query.sort === 'price-desc') {
      if (a.priceAed === null) return b.priceAed === null ? a.id.localeCompare(b.id) : 1;
      if (b.priceAed === null) return -1;
      return (Number(a.priceAed) - Number(b.priceAed)) * (query.sort === 'price-desc' ? -1 : 1) || a.id.localeCompare(b.id);
    }
    return Number(b.featured) - Number(a.featured); // stable fixture order for ties
  });
  const perPage = Number.isSafeInteger(query.perPage) && query.perPage > 0 ? query.perPage : PAGE_SIZE;
  const pageCount = Math.max(1, Math.ceil(sorted.length / perPage));
  const page = Math.min(Math.max(1, Number.isSafeInteger(query.page) ? query.page : 1), pageCount);
  return { products: sorted.slice((page - 1) * perPage, page * perPage), total: sorted.length, page, pageCount, perPage, facets: getFacets(scoped), sorts: getSorts(scoped) };
}
export { formatPrice } from '../format-price';
export const productPath = (slug: string, categoryId = 'outdoor') => {
  return `/shop/${categorySlugByPublicId[categoryId] ?? categorySlugByPublicId.outdoor}/${encodeURIComponent(slug)}`;
};
export const SHOP_PATH = '/shop';
