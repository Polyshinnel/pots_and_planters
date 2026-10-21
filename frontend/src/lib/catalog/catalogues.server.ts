import 'server-only';
import type { Catalogue } from '@/types/catalog';
import { serverApiFetch } from '@/lib/api.server';

type CatalogueListResponse = { data: CatalogueApiDto[] };
type CatalogueApiDto = {
  id: string;
  slug: string;
  title: string;
  description: string | null;
  coverImage: string | null;
  coverAlt: string | null;
  categoryIds: string[];
  categories: { id: string; name: string; slug: string }[];
};

function toCatalogue(dto: CatalogueApiDto): Catalogue {
  return { ...dto, isPublished: true };
}

export async function getCatalogues(): Promise<Catalogue[]> {
  const response = await serverApiFetch<CatalogueListResponse>('/v1/catalogues');
  return response.data.map(toCatalogue);
}

export async function getCataloguesByCategory(categorySlug: string): Promise<Catalogue[]> {
  const query = new URLSearchParams({ category: categorySlug });
  const response = await serverApiFetch<CatalogueListResponse>(`/v1/catalogues?${query}`);
  return response.data.map(toCatalogue);
}
