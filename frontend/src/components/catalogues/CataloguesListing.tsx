'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import type { Catalogue, Category } from '@/types/catalog';
import { cataloguesPath, selectCatalogues } from '@/lib/catalog/catalogues';
import { CatalogueCard } from './CatalogueCard';
import { useCatalogueDownload } from './useCatalogueDownload';
import styles from './Catalogues.module.css';

export function CataloguesListing({ catalogues, categories }: { catalogues: Catalogue[]; categories: Category[] }) {
  const params = useSearchParams();
  const category = categories.find((item) => item.slug === params.get('category'));
  const visible = selectCatalogues(catalogues, category?.id);
  const { openCatalogue, dialog } = useCatalogueDownload({ pagePath: '/catalogues', placement: 'catalogues_grid' });
  return <>
    <nav aria-label="Catalogue categories" className={styles.filters}>
      <Link href={cataloguesPath()} scroll={false} aria-current={!category ? 'page' : undefined}>All Catalogues</Link>
      {categories.map((item) => <Link key={item.id} href={cataloguesPath(item.slug)} scroll={false}
        aria-current={category?.id === item.id ? 'page' : undefined}>{item.name}</Link>)}
    </nav>
    <p className={styles.count} role="status">{visible.length} {visible.length === 1 ? 'catalogue' : 'catalogues'}{category ? ` · ${category.name}` : ''}</p>
    {visible.length ? <div className={styles.grid}>
      {visible.map((catalogue) => <CatalogueCard key={catalogue.id} catalogue={catalogue} onOpen={openCatalogue} />)}
    </div> : <section className={styles.empty}>
      <h2>No catalogues in this category yet</h2>
      <p>Explore the full collection for more ideas for your project.</p>
      <Link href={cataloguesPath()} scroll={false}>View All Catalogues</Link>
    </section>}
    {dialog}
  </>;
}
