import 'server-only';
import type { Category } from '@/types/catalog';
import { serverApiFetch } from '@/lib/api.server';

type CategoryListResponse = { data: Category[] };
type CategoryResponse = { data: Category };

export async function getCategories(): Promise<Category[]> {
  return (await serverApiFetch<CategoryListResponse>('/v1/categories')).data;
}

export async function getCategoryByPath(path: string): Promise<Category | undefined> {
  const slug = path.split('/').filter(Boolean).at(-1);
  if (!slug) return undefined;
  try {
    return (await serverApiFetch<CategoryResponse>(`/v1/categories/${encodeURIComponent(slug)}`)).data;
  } catch (error) {
    if (error instanceof Error && error.message === 'API request failed: 404') return undefined;
    throw error;
  }
}
