import 'server-only';
import { cache } from 'react';
import { serverApiFetch, ServerApiError } from '@/lib/api.server';
import type { NewsArticle, NewsRepository } from '@/types/news';

type ApiCategory = { name: string; slug: string };
type ApiArticle = {
  id: string; slug: string; title: string; excerpt: string; category: ApiCategory;
  coverImage: string | null; coverAlt: string; publishedAt: string;
  seoTitle: string; seoDescription: string; body?: string | null; ogImage?: string | null;
};
type ApiResponse<T> = { data: T };

export class NewsArticleNotFoundError extends Error {
  constructor(slug: string) { super(`Published news article not found: ${slug}`); }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requiredString(value: unknown, field: string): string {
  if (typeof value !== 'string' || value.length === 0) throw new Error(`Invalid News API payload: ${field}`);
  return value;
}

function nullableString(value: unknown, field: string): string | undefined {
  if (value === null || value === undefined) return undefined;
  return publicImageUrl(requiredString(value, field), field);
}

function publicImageUrl(value: string, field: string): string {
  if (value.startsWith('/') && !value.startsWith('//')) return value;
  let url: URL;
  try { url = new URL(value); } catch { throw new Error(`Invalid News API payload: ${field}`); }
  if (['localhost', '127.0.0.1', 'backend', 'nginx'].includes(url.hostname)) return `${url.pathname}${url.search}`;
  if (url.protocol !== 'https:') throw new Error(`Invalid News API payload: ${field}`);
  return url.href;
}

function articleFromApi(value: unknown, requireBody: boolean): NewsArticle {
  if (!isRecord(value) || !isRecord(value.category)) throw new Error('Invalid News API payload: article/category');
  const category: ApiCategory = {
    name: requiredString(value.category.name, 'category.name'),
    slug: requiredString(value.category.slug, 'category.slug'),
  };
  const date = requiredString(value.publishedAt, 'publishedAt');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(Date.parse(`${date}T00:00:00Z`))) {
    throw new Error('Invalid News API payload: publishedAt');
  }
  const body = requireBody ? requiredString(value.body, 'body') : undefined;
  const coverImage = nullableString(value.coverImage, 'coverImage');
  if (!coverImage) throw new Error('Invalid News API payload: coverImage');
  return {
    id: requiredString(value.id, 'id'), slug: requiredString(value.slug, 'slug'),
    title: requiredString(value.title, 'title'), excerpt: requiredString(value.excerpt, 'excerpt'),
    category, coverImage, coverAlt: requiredString(value.coverAlt, 'coverAlt'), publishedAt: date,
    seoTitle: requiredString(value.seoTitle, 'seoTitle'), seoDescription: requiredString(value.seoDescription, 'seoDescription'),
    body, ogImage: nullableString(value.ogImage, 'ogImage'), status: 'published',
  };
}

function responseData<T>(value: unknown, validate: (data: unknown) => T): T {
  if (!isRecord(value) || !Object.hasOwn(value, 'data')) throw new Error('Invalid News API response envelope');
  return validate(value.data);
}

const getPublished = cache(async (): Promise<NewsArticle[]> => {
  return responseData(await serverApiFetch<ApiResponse<unknown>>('/v1/news'), (data) => {
    if (!Array.isArray(data)) throw new Error('Invalid News API payload: data must be an array');
    return data.map((article) => articleFromApi(article, false));
  });
});

const getBySlug = cache(async (slug: string): Promise<NewsArticle> => {
  try {
    return responseData(await serverApiFetch<ApiResponse<unknown>>(`/v1/news/${encodeURIComponent(slug)}`), (data) => articleFromApi(data, true));
  } catch (error) {
    if (error instanceof ServerApiError && error.status === 404) throw new NewsArticleNotFoundError(slug);
    throw error;
  }
});

export const newsRepository: NewsRepository = { getPublished, getBySlug };

export function formatNewsDate(date: string): string {
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(`${date}T00:00:00Z`));
}
