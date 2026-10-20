import { getStaticPageMetadata } from '@/lib/page-seo.server';
import { CatalogPage, type SearchParams } from '@/components/catalog/CatalogPage';
export const generateMetadata = () => getStaticPageMetadata('shop');
export const dynamic = 'force-dynamic';
export default async function Shop({ searchParams }: { searchParams: Promise<SearchParams> }) {
  return <CatalogPage searchParams={await searchParams} />;
}
