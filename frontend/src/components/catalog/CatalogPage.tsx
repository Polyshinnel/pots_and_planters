import Link from 'next/link';
import { Container } from '@mantine/core';
import { ServerApiError } from '@/lib/api.server';
import type { Category } from '@/types/catalog';
import { getProducts } from '@/lib/catalog/products.server';
import { getCatalogues, getCataloguesByCategory } from '@/lib/catalog/catalogues.server';
import { getCategories } from '@/lib/catalog/categories.server';
import { PAGE_SIZE, parseFilterParams, queryString, SHOP_PATH } from '@/lib/catalog/domain';
import { CatalogListing } from './CatalogListing';
import { CategoryCataloguesCTA } from './CategoryCataloguesCTA';
import { CatalogErrorState } from './CatalogErrorState';
import styles from './Catalog.module.css';

const categoryIntro: Record<string, { title: string; paragraphs: string[] }> = {
  shop: {
    title: 'Pots, Planters & Artificial Greenery for Projects Across the UAE',
    paragraphs: [
      'Explore our selection of indoor and outdoor pots, decorative planters and artificial greenery for residential and commercial spaces across Dubai and the UAE. Our collections include a variety of shapes, materials, sizes and finishes for hotels, restaurants, offices, landscaped areas and private villas.',
      'Pots & Planters works with architects, interior designers and project teams to support product selection for spaces of different styles and scales. Browse our collections, explore our product catalogues or contact our Dubai-based team to discuss your project requirements.',
    ],
  },
};

export type SearchParams = Record<string, string | string[] | undefined>;
export async function CatalogPage({ category, searchParams }: { category?: Category; searchParams: SearchParams }) {
  const path = category?.path ?? SHOP_PATH;
  const params = new URLSearchParams();
  for (const [key, values] of Object.entries(searchParams)) for (const value of Array.isArray(values) ? values : values ? [values] : []) params.append(key, value);
  const sortValues = ['newest', 'name-asc', 'name-desc', 'price-asc', 'price-desc'] as const;
  const sort = params.get('sort') ?? 'newest';
  const pageText = params.get('page') ?? '1';
  const invalidQuery = params.getAll('sort').length > 1 || params.getAll('page').length > 1 || !sortValues.includes(sort as typeof sortValues[number]) || !/^[1-9]\d*$/.test(pageText) || !Number.isSafeInteger(Number(pageText));
  const legacyKeys = ['material', 'color', 'size', 'usage'];
  const unsupportedFilters = [...params.keys()].some((key) => legacyKeys.includes(key));
  const parsed = parseFilterParams(params);
  const invalidFilter = parsed.invalid;
  const categories = await getCategories();
  const catalogues = await (category ? getCataloguesByCategory(category.slug) : getCatalogues());
  const query = { categoryId: category?.id, filters: parsed.filters, sort: (sortValues.includes(sort as typeof sortValues[number]) ? sort : 'newest') as typeof sortValues[number], page: Number(pageText), perPage: PAGE_SIZE };
  let result: Awaited<ReturnType<typeof getProducts>> | null = null;
  let apiErrorStatus: number | undefined;
  if (!invalidQuery && !unsupportedFilters && !invalidFilter) {
    try {
      result = await getProducts(query, category?.slug);
    } catch (error) {
      if (error instanceof ServerApiError) apiErrorStatus = error.status;
      else throw error;
    }
  }
  const clearParams = new URLSearchParams(params);
  for (const key of [...clearParams.keys()]) if (legacyKeys.includes(key) || key.startsWith('filter')) clearParams.delete(key);
  clearParams.delete('page');
  if (params.getAll('sort').length > 1 || !sortValues.includes(sort as typeof sortValues[number])) clearParams.delete('sort');
  const clearQuery = clearParams.toString();
  const intro = category ? (category.introHeading && category.introParagraphs?.length ? { title: category.introHeading, paragraphs: category.introParagraphs } : undefined) : categoryIntro.shop;
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li>
      <li>{category ? <Link href={SHOP_PATH}>Shop</Link> : <span aria-current="page">Shop</span>}</li>
      {category && <li><span aria-current="page">{category.name}</span></li>}
    </ol></nav>
    <header className={styles.heading}><h1>{category?.heading ?? 'Pots, Planters & Artificial Greenery in the UAE'}</h1>
      <p>{category?.description ?? 'Explore pots, planters and artificial greenery for your next project.'}</p>
    </header>
    {apiErrorStatus && apiErrorStatus !== 422 ? <CatalogErrorState status={apiErrorStatus} /> : null}
    {apiErrorStatus === 422 ? <CatalogErrorState status={422} clearHref={`${path}${clearQuery ? `?${clearQuery}` : ''}`} /> : null}
    <CatalogListing categories={categories} categoryId={category?.id} path={path} query={query} result={result} canonicalQuery={queryString(query, params.has('sort'), params.has('page'), params)} preserveParams={params} filterUnavailable={unsupportedFilters} clearHref={`${path}${clearQuery ? `?${clearQuery}` : ''}`} validationError={invalidQuery || invalidFilter} />
    <CategoryCataloguesCTA catalogues={catalogues} category={category} />
    {intro && <section className={styles.categoryIntro} aria-labelledby="category-intro-title">
      <h2 id="category-intro-title">{intro.title}</h2>
      {intro.paragraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
    </section>}
  </Container>;
}
