import type { Metadata } from 'next';
import { CatalogPage, type SearchParams } from '@/components/catalog/CatalogPage';
import { SHOP_PATH } from '@/lib/catalog/domain';

// Canonical extension point: queries do not become independent category identities.
export const metadata: Metadata = { title: 'Planters & Artificial Plants Supplier in UAE | Pots & Planters', description: 'Explore pots, planters and artificial greenery.', alternates: { canonical: SHOP_PATH } };
export default async function Shop({ searchParams }: { searchParams: Promise<SearchParams> }) {
  return <CatalogPage searchParams={await searchParams} />;
}
