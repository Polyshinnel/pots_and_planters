import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { CatalogPage, type SearchParams } from '@/components/catalog/CatalogPage';
import { getCategoryByPath } from '@/lib/catalog/categories.server';

type Props = { params: Promise<{ category: string }>; searchParams: Promise<SearchParams> };
async function resolveCategory(params: Props['params']) {
  const { category } = await params;
  if (category === 'art-plants') permanentRedirect('/shop/artificial-plants');
  const found = await getCategoryByPath(`/shop/${category}`);
  if (!found) notFound();
  return found;
}
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const category = await resolveCategory(params);
  const description = category.seoDescription ?? category.description;
  return { title: category.seoTitle, description, alternates: { canonical: category.path }, openGraph: { type: 'website', url: category.path, title: category.seoTitle, description, ...(category.ogImage ? { images: [category.ogImage] } : {}) } };
}
export default async function Category({ params, searchParams }: Props) {
  return <CatalogPage category={await resolveCategory(params)} searchParams={await searchParams} />;
}
