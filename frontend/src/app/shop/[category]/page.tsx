import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { CatalogPage, type SearchParams } from '@/components/catalog/CatalogPage';
import { catalogRepository } from '@/lib/catalog/repository';

type Props = { params: Promise<{ category: string }>; searchParams: Promise<SearchParams> };
async function resolveCategory(params: Props['params']) {
  const { category } = await params;
  if (category === 'art-plants') permanentRedirect('/shop/artificial-plants');
  const found = await catalogRepository.getCategoryByPath(`/shop/${category}`);
  if (!found) notFound();
  return found;
}
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const category = await resolveCategory(params);
  return { title: category.seoTitle, description: category.description, alternates: { canonical: category.path } };
}
export default async function Category({ params, searchParams }: Props) {
  return <CatalogPage category={await resolveCategory(params)} searchParams={await searchParams} />;
}
