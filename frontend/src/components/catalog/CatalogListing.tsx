'use client';

import Link from 'next/link';
import { useEffect, useOptimistic, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { Button, Drawer, NativeSelect, useMantineTheme } from '@mantine/core';
import { IconAdjustmentsHorizontal } from '@tabler/icons-react';
import type { Category, CatalogFacet, CatalogFilters, CatalogQuery, CatalogResult } from '@/types/catalog';
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
function FilterGroups({ facets, filters, onChange }: { facets: CatalogFacet[]; filters: CatalogFilters; onChange: (filters: CatalogFilters) => void }) {
  const update = (code: string, value: string, checked: boolean) => {
    const selected = filters[code]; const values = Array.isArray(selected) ? selected : [];
    const next = checked ? [...new Set([...values, value])] : values.filter((item) => item !== value);
    const result = { ...filters }; if (next.length) result[code] = next; else delete result[code]; onChange(result);
  };
  return <div className={styles.filterGroups}>{facets.map((facet) => {
    const active = filters[facet.code];
    if (facet.type === 'number') {
      if (facet.min === null && facet.max === null && !active) return null;
      const range = Array.isArray(active) ? {} : active ?? {};
      return <fieldset key={facet.code}><legend>{facet.label}</legend>
        <label>Min<input type="text" inputMode="decimal" aria-label={`${facet.label} minimum`} value={range.min ?? ''} placeholder={facet.min ?? ''} onChange={(event) => { const next = { ...range, ...(event.target.value ? { min: event.target.value } : {}) }; if (!event.target.value) delete next.min; const result = { ...filters }; if (next.min === undefined && next.max === undefined) delete result[facet.code]; else result[facet.code] = next; onChange(result); }} /></label>
        <label>Max<input type="text" inputMode="decimal" aria-label={`${facet.label} maximum`} value={range.max ?? ''} placeholder={facet.max ?? ''} onChange={(event) => { const next = { ...range, ...(event.target.value ? { max: event.target.value } : {}) }; if (!event.target.value) delete next.max; const result = { ...filters }; if (next.min === undefined && next.max === undefined) delete result[facet.code]; else result[facet.code] = next; onChange(result); }} /></label>
      </fieldset>;
    }
    const options = [...(facet.options ?? [])];
    for (const value of Array.isArray(active) ? active : []) if (!options.some((option) => option.value === value)) options.push({ value, label: value, count: 0 });
    if (facet.type === 'text') return <fieldset key={facet.code}><legend>{facet.label}</legend><label>{facet.label} exact match<input type="text" value={Array.isArray(active) ? active[0] ?? '' : ''} onChange={(event) => { const result = { ...filters }; if (event.target.value) result[facet.code] = [event.target.value]; else delete result[facet.code]; onChange(result); }} /></label></fieldset>;
    return <fieldset key={facet.code}><legend>{facet.label}</legend>{options.map((option) => <label key={option.value} className={styles.checkbox}>
      <input type="checkbox" checked={Array.isArray(active) && active.includes(option.value)} onChange={(event) => update(facet.code, option.value, event.target.checked)} />
      <span>{option.label} ({option.count})</span>
    </label>)}</fieldset>;
  })}</div>;
}
export function CatalogListing({ categories, categoryId, path, query, result, canonicalQuery, filterUnavailable = false, clearHref = path, validationError = false, preserveParams }: {
  categories: Category[]; categoryId?: string; path: string; query: CatalogQuery; result: CatalogResult | null; canonicalQuery: string;
  filterUnavailable?: boolean; clearHref?: string; validationError?: boolean; preserveParams?: URLSearchParams;
}) {
  const router = useRouter();
  const theme = useMantineTheme();
  const [pending, startTransition] = useTransition();
  const [displayQuery, setOptimisticQuery] = useOptimistic(query);
  const [opened, setOpened] = useState(false);
  const [draft, setDraft] = useState<CatalogFilters>({});
  // Ignore unsupported filter URLs; keep them intact until the visitor explicitly clears them.
  useEffect(() => {
    if (!filterUnavailable && !validationError && !pending && window.location.pathname === path && window.location.search !== canonicalQuery) router.replace(path + canonicalQuery, { scroll: false });
  }, [path, canonicalQuery, pending, router, filterUnavailable, validationError]);
  const navigate = (next: CatalogQuery) => startTransition(() => {
    setOptimisticQuery(next);
    router.push(path + queryString(next, false, false, preserveParams), { scroll: false });
  });
  const changeFilters = (filters: CatalogFilters) => navigate({ ...displayQuery, filters, page: 1 });
  const selected = Object.values(displayQuery.filters).reduce((count, values) => count + (Array.isArray(values) ? values.length : Number(values.min !== undefined) + Number(values.max !== undefined)), 0);
  const clear = () => changeFilters({});
  return <div className={styles.layout}>
    <aside className={styles.sidebar} aria-label="Catalogue navigation and filters">
      <CategoryLinks categories={categories} categoryId={categoryId} />
      <div aria-busy={pending}><fieldset className={styles.filterControls}>
        {!filterUnavailable && !validationError && result && <FilterGroups facets={result.facets} filters={displayQuery.filters} onChange={changeFilters} />}
        {filterUnavailable && <p>This URL uses an unsupported legacy filter. Clear filters to view products.</p>}
        {filterUnavailable && <Button component={Link} href={clearHref} variant="light" fullWidth>Clear filters</Button>}
        {validationError && <Button component={Link} href={clearHref} variant="light" fullWidth>Reset catalog options</Button>}
      </fieldset></div>
    </aside>
    <section className={styles.results} aria-label="Product listing" aria-busy={pending}>
      {validationError ? <p role="alert">The catalog page, sort option or filter value is invalid. Please choose valid catalog options.</p> : filterUnavailable ? <p role="status">This URL uses an unsupported legacy filter. Clear filters to view products.</p> : !result ? null : <>
      <div className={styles.toolbar}>
        <p className={styles.count} role="status" aria-live="polite">{pending ? 'Updating products…' : result.total ? `${(result.page - 1) * result.perPage + 1}–${Math.min(result.page * result.perPage, result.total)} of ${result.total} products` : '0 products'}</p>
        <Button className={styles.mobileTrigger} variant="outline" leftSection={<IconAdjustmentsHorizontal size={18} aria-hidden="true" />} onClick={() => { setDraft(displayQuery.filters); setOpened(true); }} aria-haspopup="dialog" aria-expanded={opened}>Filters{selected ? ` (${selected})` : ''}</Button>
        <NativeSelect className={styles.sort} label="Sort by" value={displayQuery.sort} data={result.sorts} onChange={(event) => navigate({ ...displayQuery, sort: event.currentTarget.value as CatalogQuery['sort'], page: 1 })} />
      </div>
      {selected > 0 && <div className={styles.applied} aria-label="Applied filters">
        {Object.entries(displayQuery.filters).flatMap(([key, values]) => Array.isArray(values) ? values.map((value) => <button key={`${key}-${value}`} onClick={() => changeFilters({ ...displayQuery.filters, [key]: values.filter((item) => item !== value) })} aria-label={`Remove ${key}: ${value}`}>{key}: {value} <span aria-hidden="true">×</span></button>) : Object.entries(values).map(([bound, value]) => <button key={`${key}-${bound}`} onClick={() => { const next = { ...displayQuery.filters }; const range = { ...values }; delete range[bound as 'min' | 'max']; if (!range.min && !range.max) delete next[key]; else next[key] = range; changeFilters(next); }} aria-label={`Remove ${key} ${bound}: ${value}`}>{key} {bound}: {value} <span aria-hidden="true">×</span></button>))}
        <button onClick={clear}>Clear filters</button>
      </div>}
      {result.total > 0 ? <div className={styles.grid}>{result.products.map((product) => <CatalogCard key={product.id} product={product} />)}</div>
        : <div className={styles.empty}><h2>No products match these filters</h2><p>Try fewer filters or explore another category.</p><Button onClick={clear}>Clear filters</Button></div>}
      {result.pageCount > 1 && <nav aria-label="Product pagination" className={styles.pagination}>
        {Array.from({ length: result.pageCount }, (_, index) => index + 1).map((page) => <Link key={page} href={path + queryString({ ...query, page }, false, false, preserveParams)} scroll={false} aria-label={`Page ${page}`} aria-current={page === result.page ? 'page' : undefined}>{page}</Link>)}
      </nav>}
      </>}
    </section>
    <Drawer opened={opened} onClose={() => setOpened(false)} title="Categories & filters" closeButtonProps={{ 'aria-label': 'Close filters' }} position="left" size="min(100%, 420px)" zIndex={theme.other.layers.drawer} trapFocus returnFocus closeOnEscape lockScroll styles={{ body: { display: 'flex', flexDirection: 'column', height: 'calc(100dvh - 76px)', paddingBottom: 0 } }}>
      <div className={styles.drawerScroll}>
        <CategoryLinks categories={categories} categoryId={categoryId} close={() => setOpened(false)} />
        {!filterUnavailable && !validationError && result && <FilterGroups facets={result.facets} filters={draft} onChange={setDraft} />}
      </div>
      <div className={styles.drawerActions}>
        {filterUnavailable || validationError ? <Button component={Link} href={clearHref} variant="light">{validationError ? 'Reset catalog options' : 'Clear filters'}</Button> : <Button variant="light" onClick={() => setDraft({})}>Clear filters</Button>}
        <Button disabled={filterUnavailable || validationError} onClick={() => { changeFilters(draft); setOpened(false); }}>Show products</Button>
      </div>
    </Drawer>
  </div>;
}
