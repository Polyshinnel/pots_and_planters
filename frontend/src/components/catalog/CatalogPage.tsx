import Link from 'next/link';
import { Container } from '@mantine/core';
import type { Category } from '@/types/catalog';
import { catalogRepository } from '@/lib/catalog/repository';
import { parseQuery, PAGE_SIZE, queryString, SHOP_PATH } from '@/lib/catalog/domain';
import { CatalogListing } from './CatalogListing';
import { CategoryCataloguesCTA } from './CategoryCataloguesCTA';
import styles from './Catalog.module.css';

const categoryIntro: Record<string, { title: string; paragraphs: string[] }> = {
  'outdoor-pots': {
    title: 'Outdoor Planters for Hotels, Villas and Commercial Projects',
    paragraphs: [
      'Explore outdoor pots and planters for terraces, entrances, landscaped areas and hospitality spaces across Dubai and the UAE. Our collection includes a variety of shapes, finishes and sizes suitable for different architectural concepts.',
      'We work with designers, architects and project teams to help select planters that complement the surrounding space. Browse the collection or contact our team to discuss product specifications and project requirements.',
    ],
  },
  'indoor-pots': {
    title: 'Indoor Planters for Hotels, Offices and Residential Interiors',
    paragraphs: [
      'Explore our collection of indoor pots and decorative planters for hotels, restaurants, offices, private villas and residential interiors across Dubai and the UAE. Discover a variety of shapes, sizes and finishes designed to complement contemporary, minimalist and classic spaces.',
      'We work with interior designers, architects and project teams to help select planters that suit their design concepts and spatial requirements. Whether you are furnishing a hotel lobby, creating greenery for an office or completing a residential project, our team can assist with product selection and specifications.',
    ],
  },
  'artificial-plants': {
    title: 'Artificial Plants & Trees for Commercial and Residential Projects',
    paragraphs: [
      'Discover artificial plants and decorative trees for hotels, restaurants, offices, retail spaces and private residences across Dubai and the UAE. Our collection offers greenery solutions for interiors and other suitable spaces where natural plants may be difficult to maintain or where a consistent appearance is preferred.',
      'We help architects, interior designers and project teams select artificial greenery that complements their spaces and design requirements. Explore decorative plants, trees and arrangements for hospitality, commercial and residential projects, or contact our team to discuss suitable options for your project.',
    ],
  },
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
  const categories = await catalogRepository.getCategories();
  const catalogues = await (category ? catalogRepository.getCataloguesByCategory(category.id) : catalogRepository.getCatalogues());
  const scope = await catalogRepository.getProducts({ categoryId: category?.id, filters: {}, sort: 'featured', page: 1, perPage: PAGE_SIZE });
  const params = new URLSearchParams();
  for (const [key, values] of Object.entries(searchParams)) for (const value of Array.isArray(values) ? values : values ? [values] : []) params.append(key, value);
  const query = parseQuery(params, scope.facets, scope.sorts, category?.id);
  const result = await catalogRepository.getProducts(query);
  query.page = result.page;
  const path = category?.path ?? SHOP_PATH;
  const intro = categoryIntro[category?.slug ?? 'shop'];
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li>
      <li>{category ? <Link href={SHOP_PATH}>Shop</Link> : <span aria-current="page">Shop</span>}</li>
      {category && <li><span aria-current="page">{category.name}</span></li>}
    </ol></nav>
    <header className={styles.heading}><h1>{category?.heading ?? 'Pots, Planters & Artificial Greenery in the UAE'}</h1>
      <p>{category?.description ?? 'Explore pots, planters and artificial greenery for your next project.'}</p>
    </header>
    <CatalogListing categories={categories} categoryId={category?.id} path={path} query={query} result={result} canonicalQuery={queryString(query)} />
    <CategoryCataloguesCTA catalogues={catalogues} category={category} />
    {intro && <section className={styles.categoryIntro} aria-labelledby="category-intro-title">
      <h2 id="category-intro-title">{intro.title}</h2>
      {intro.paragraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
    </section>}
  </Container>;
}
