'use client';

import Link from 'next/link';
import { useEffect, useOptimistic, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { Button, Drawer, NativeSelect, useMantineTheme } from '@mantine/core';
import { IconAdjustmentsHorizontal } from '@tabler/icons-react';
import type { Category, CatalogFilters, CatalogQuery, CatalogResult } from '@/types/catalog';
import { queryString, SHOP_PATH } from '@/lib/catalog/domain';
import { CatalogCard } from './CatalogCard';
import styles from './Catalog.module.css';

function CategoryLinks({ categories, categoryId, close }: { categories: Category[]; categoryId?: string; close?: () => void }) {
  return <nav aria-label="Product categories" className={styles.categories}>
    <h2>Categories</h2>
    <Link href={SHOP_PATH} onClick={close} aria-current={!categoryId ? 'page' : undefined}>All products</Link>
    <ul>{categories.map((category) => <li key={category.id}>
      <Link href={category.path} onClick={close} aria-current={categoryId === category.id ? 'page' : undefined}>{category.name}</Link>
    </li>)}</ul>
  </nav>;
}
function FilterGroups({ facets, filters, onChange }: { facets: CatalogResult['facets']; filters: CatalogFilters; onChange: (filters: CatalogFilters) => void }) {
  return <div className={styles.filterGroups}>{facets.map((facet) => <fieldset key={facet.key}>
    <legend>{facet.label}</legend>
    {facet.values.map((value) => <label key={value} className={styles.checkbox}>
      <input type="checkbox" checked={filters[facet.key]?.includes(value) ?? false} onChange={(event) => {
        const values = filters[facet.key] ?? [];
        onChange({ ...filters, [facet.key]: event.target.checked ? [...values, value] : values.filter((item) => item !== value) });
      }} />
      <span>{value}</span>
    </label>)}
  </fieldset>)}</div>;
}
export function CatalogListing({ categories, categoryId, path, query, result, canonicalQuery }: {
  categories: Category[]; categoryId?: string; path: string; query: CatalogQuery; result: CatalogResult; canonicalQuery: string;
}) {
  const router = useRouter();
  const theme = useMantineTheme();
  const [pending, startTransition] = useTransition();
  const [displayQuery, setOptimisticQuery] = useOptimistic(query);
  const [opened, setOpened] = useState(false);
  const [draft, setDraft] = useState<CatalogFilters>({});
  // Ignore invalid values and replace noncanonical ordering/defaults without adding history entries.
  useEffect(() => {
    if (!pending && window.location.pathname === path && window.location.search !== canonicalQuery) router.replace(path + canonicalQuery, { scroll: false });
  }, [path, canonicalQuery, pending, router]);
  const navigate = (next: CatalogQuery) => startTransition(() => {
    setOptimisticQuery(next);
    router.push(path + queryString(next), { scroll: false });
  });
  const changeFilters = (filters: CatalogFilters) => navigate({ ...displayQuery, filters, page: 1 });
  const selected = Object.values(displayQuery.filters).reduce((count, values) => count + values.length, 0);
  const clear = () => changeFilters({});
  return <div className={styles.layout}>
    <aside className={styles.sidebar} aria-label="Catalogue navigation and filters">
      <CategoryLinks categories={categories} categoryId={categoryId} />
      <div aria-busy={pending}><fieldset className={styles.filterControls}>
        <FilterGroups facets={result.facets} filters={displayQuery.filters} onChange={changeFilters} />
        <Button variant="light" fullWidth onClick={clear} disabled={!selected}>Clear filters</Button>
      </fieldset></div>
    </aside>
    <section className={styles.results} aria-label="Product listing" aria-busy={pending}>
      <div className={styles.toolbar}>
        <p className={styles.count} role="status" aria-live="polite">{pending ? 'Updating products…' : result.total ? `${(result.page - 1) * result.perPage + 1}–${Math.min(result.page * result.perPage, result.total)} of ${result.total} products` : '0 products'}</p>
        <Button className={styles.mobileTrigger} variant="outline" leftSection={<IconAdjustmentsHorizontal size={18} aria-hidden="true" />} onClick={() => { setDraft(displayQuery.filters); setOpened(true); }} aria-haspopup="dialog" aria-expanded={opened}>Filters{selected ? ` (${selected})` : ''}</Button>
        <NativeSelect className={styles.sort} label="Sort by" value={displayQuery.sort} data={result.sorts} onChange={(event) => navigate({ ...displayQuery, sort: event.currentTarget.value as CatalogQuery['sort'], page: 1 })} />
      </div>
      {selected > 0 && <div className={styles.applied} aria-label="Applied filters">
        {Object.entries(displayQuery.filters).flatMap(([key, values]) => values.map((value) => <button key={`${key}-${value}`} onClick={() => changeFilters({ ...displayQuery.filters, [key]: values.filter((item) => item !== value) })} aria-label={`Remove ${key}: ${value}`}>{key}: {value} <span aria-hidden="true">×</span></button>))}
        <button onClick={clear}>Clear filters</button>
      </div>}
      {result.total > 0 ? <div className={styles.grid}>{result.products.map((product) => <CatalogCard key={product.id} product={product} />)}</div>
        : <div className={styles.empty}><h2>No products match these filters</h2><p>Try fewer filters or explore another category.</p><Button onClick={clear}>Clear filters</Button></div>}
      {result.pageCount > 1 && <nav aria-label="Product pagination" className={styles.pagination}>
        {Array.from({ length: result.pageCount }, (_, index) => index + 1).map((page) => <Link key={page} href={path + queryString({ ...query, page })} scroll={false} aria-label={`Page ${page}`} aria-current={page === result.page ? 'page' : undefined}>{page}</Link>)}
      </nav>}
    </section>
    <Drawer opened={opened} onClose={() => setOpened(false)} title="Categories & filters" closeButtonProps={{ 'aria-label': 'Close filters' }} position="left" size="min(100%, 420px)" zIndex={theme.other.layers.drawer} trapFocus returnFocus closeOnEscape lockScroll styles={{ body: { display: 'flex', flexDirection: 'column', height: 'calc(100dvh - 76px)', paddingBottom: 0 } }}>
      <div className={styles.drawerScroll}>
        <CategoryLinks categories={categories} categoryId={categoryId} close={() => setOpened(false)} />
        <FilterGroups facets={result.facets} filters={draft} onChange={setDraft} />
      </div>
      <div className={styles.drawerActions}>
        <Button variant="light" onClick={() => setDraft({})}>Clear filters</Button>
        <Button onClick={() => { changeFilters(draft); setOpened(false); }}>Show products</Button>
      </div>
    </Drawer>
  </div>;
}
