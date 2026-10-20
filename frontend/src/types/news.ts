export type NewsCategory = 'Projects & Collaborations' | 'Company News' | 'Events & Exhibitions';
export type NewsInline = { type: 'text'; text: string } | { type: 'link'; text: string; href: string };
export type NewsBlock =
  | { type: 'paragraph'; children: NewsInline[] }
  | { type: 'heading'; level: 2 | 3; text: string }
  | { type: 'links'; links: { label: string; href: string }[] };

/** JSON-serializable contract for a future Laravel/Filament news resource. */
export interface NewsArticle {
  id: string;
  slug: string;
  title: string;
  excerpt: string;
  body: NewsBlock[];
  originalCopy: string;
  coverImage: string;
  coverAlt: string;
  sourceImage: { filename: string; width: number; height: number };
  category: NewsCategory;
  /** Original publication date, YYYY-MM-DD; no fabricated time of day. */
  publishedAt: string;
  status: 'published' | 'draft';
  seoTitle: string;
  seoDescription: string;
  ogImage?: string;
  externalLinks?: { label: string; href: string }[];
  relatedEntities?: { type: 'product' | 'solution'; id: string }[];
}

export interface NewsRepository {
  getPublished(): Promise<NewsArticle[]>;
  getBySlug(slug: string): Promise<NewsArticle | null>;
}
