import 'server-only';
import type { Metadata } from 'next';
import { serverApiFetch } from '@/lib/api.server';

const PAGE_PATHS = {
  home: '/',
  shop: '/shop',
  solutions: '/solutions',
  catalogues: '/catalogues',
  news: '/news',
  about: '/about',
  contacts: '/contacts',
  private_policy: '/private-policy',
} as const;

type PageKey = keyof typeof PAGE_PATHS;
type PageSeoResponse = {
  data: { pageKey: PageKey; title: string; description: string; ogImageUrl: string | null };
};

export async function getStaticPageMetadata(pageKey: PageKey): Promise<Metadata> {
  const canonical = PAGE_PATHS[pageKey];
  let seo: PageSeoResponse['data'];

  try {
    ({ data: seo } = await serverApiFetch<PageSeoResponse>(`/v1/page-seo/${pageKey}`));
  } catch (error) {
    console.error(`Unable to load SEO metadata for ${pageKey}:`, error instanceof Error ? error.message : 'Unknown API error');
    throw error;
  }

  // Keep the existing archive image when the News SEO record has no upload.
  // This is an image-only default; title and description always come from the API.
  const ogImageUrl = seo.ogImageUrl ?? (pageKey === 'news' ? '/images/news/6.png' : null);

  return {
    title: seo.title,
    description: seo.description,
    alternates: { canonical },
    openGraph: {
      type: 'website',
      url: canonical,
      title: seo.title,
      description: seo.description,
      ...(pageKey === 'news' ? { siteName: 'Pots & Planters', locale: 'en_GB' } : {}),
      ...(ogImageUrl ? {
        images: [{
          url: ogImageUrl,
          ...(pageKey === 'news' ? { alt: 'Greenery and a ribbed planter with Jumeirah Marsa Al Arab lettering' } : {}),
        }],
      } : {}),
    },
    twitter: {
      card: ogImageUrl ? 'summary_large_image' : 'summary',
      title: seo.title,
      description: seo.description,
      ...(ogImageUrl ? { images: [ogImageUrl] } : {}),
    },
  };
}
