import type { Metadata } from 'next';
import type { NewsArticle } from '@/types/news';

export function newsMetadata(article?: NewsArticle): Metadata {
  const path = article ? `/news/${article.slug}` : '/news';
  const title = article ? article.seoTitle : 'News & Insights | Pots & Planters';
  const description = article?.seoDescription ?? 'Explore the Pots & Planters news archive: project announcements, company updates and exhibitions from 2023–2025 in Dubai and the UAE.';
  const image = article?.ogImage ?? article?.coverImage ?? '/images/news/6.png';
  return {
    title, description, alternates: { canonical: path },
    openGraph: {
      type: article ? 'article' : 'website', title, description, url: path,
      siteName: 'Pots & Planters', locale: 'en_GB',
      images: [{ url: image, alt: article?.coverAlt ?? 'Greenery and a ribbed planter with Jumeirah Marsa Al Arab lettering' }],
      ...(article ? { publishedTime: article.publishedAt, section: article.category.name } : {}),
    },
    twitter: { card: 'summary_large_image', title, description, images: [image] },
  };
}
