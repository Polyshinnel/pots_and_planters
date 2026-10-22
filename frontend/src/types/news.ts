export type NewsCategory = { name: string; slug: string };
/** Public Laravel News API contract consumed by server components. */
export interface NewsArticle {
  id: string;
  slug: string;
  title: string;
  excerpt: string;
  body?: string;
  coverImage: string;
  coverAlt: string;
  category: NewsCategory;
  /** Original publication date, YYYY-MM-DD; no fabricated time of day. */
  publishedAt: string;
  status: 'published';
  seoTitle: string;
  seoDescription: string;
  ogImage?: string;
}

export interface NewsRepository {
  getPublished(): Promise<NewsArticle[]>;
  getBySlug(slug: string): Promise<NewsArticle>;
}
