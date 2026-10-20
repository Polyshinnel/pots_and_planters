import { newsArticles } from '@/data/news.mock';
import type { NewsArticle, NewsRepository } from '@/types/news';

/** Same-day records use stable IDs in source order, without invented timestamps. */
export function publishedNews(records: readonly NewsArticle[]): NewsArticle[] {
  return records.filter((article) => article.status === 'published').sort((a, b) =>
    b.publishedAt.localeCompare(a.publishedAt) || a.id.localeCompare(b.id));
}

/** Replace only this adapter when the Laravel API becomes available. */
export const newsRepository: NewsRepository = {
  async getPublished() { return publishedNews(newsArticles); },
  async getBySlug(slug) {
    return newsArticles.find((article) => article.slug === slug && article.status === 'published') ?? null;
  },
};

export function formatNewsDate(date: string): string {
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(`${date}T00:00:00Z`));
}
