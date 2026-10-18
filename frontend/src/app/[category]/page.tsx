import { notFound, permanentRedirect } from 'next/navigation';
import { categories } from '@/config/catalog';

type SearchParams = Record<string, string | string[] | undefined>;

export default async function LegacyCategoryRedirect({ params, searchParams }: {
  params: Promise<{ category: string }>;
  searchParams: Promise<SearchParams>;
}) {
  const { category: slug } = await params;
  const category = categories.find((item) => item.slug === slug || (item.id === 'artificial' && slug === 'art-plants'));
  if (!category) notFound();

  const query = new URLSearchParams();
  for (const [key, values] of Object.entries(await searchParams)) {
    for (const value of Array.isArray(values) ? values : values ? [values] : []) query.append(key, value);
  }
  const search = query.toString();
  permanentRedirect(`${category.path}${search ? `?${search}` : ''}`);
}
