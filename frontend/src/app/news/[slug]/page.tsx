import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Container } from '@mantine/core';
import { newsRepository, formatNewsDate, NewsArticleNotFoundError } from '@/lib/news/repository';
import { newsMetadata } from '@/lib/news/seo';
import { NewsContact } from '@/components/news/NewsContact';
import pageStyles from '@/components/catalog/Catalog.module.css';
import styles from '@/components/news/News.module.css';

type Props = { params: Promise<{ slug: string }> };
export const dynamic = 'force-dynamic';

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const article = await findArticle((await params).slug);
  return newsMetadata(article);
}

export default async function NewsArticlePage({ params }: Props) {
  const article = await findArticle((await params).slug);
  // Matches the production metadataBase configured in the root layout.
  const canonical = `https://potsandplanters.ae/news/${article.slug}`;
  const jsonLd = {
    '@context': 'https://schema.org', '@type': 'NewsArticle',
    headline: article.title, datePublished: article.publishedAt,
    image: new URL(article.ogImage ?? article.coverImage, canonical).href,
    url: canonical, mainEntityOfPage: { '@type': 'WebPage', '@id': canonical },
    articleSection: article.category.name,
  };
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><Link href="/news">News</Link></li>
      <li><span aria-current="page">{article.title}</span></li>
    </ol></nav>
    <article className={styles.article}>
      <figure className={styles.cover}><Image src={article.coverImage} alt={article.coverAlt} width={848} height={848} preload unoptimized={article.coverImage.includes('/storage/')} sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1200px) 45vw, 50vw" /></figure>
      <div className={styles.articleContent}>
      <header className={styles.articleHeader}>
        <div className={styles.meta}><span>{article.category.name}</span><time dateTime={article.publishedAt}>{formatNewsDate(article.publishedAt)}</time></div>
        <h1>{article.title}</h1>
        <p className={styles.archiveNote}>From the Pots &amp; Planters archive. The original company announcement below was published on {formatNewsDate(article.publishedAt)}; statements reflect that date.
          {article.id === 'news-5' && ' INDEX Dubai 2025 took place on 27–29 May 2025. This is a past event.'}
          {article.id === 'news-4' && ' This 2024 service announcement does not confirm current service availability. Contact our team for current options.'}
        </p>
      </header>
      <div className={styles.body} dangerouslySetInnerHTML={{ __html: article.body ?? '' }} />
      <footer className={styles.articleFooter}><Link className={styles.textLink} href="/news"><span aria-hidden="true">←</span> Back to News</Link></footer>
      </div>
    </article>
    <NewsContact />
    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }} />
  </Container>;
}

async function findArticle(slug: string) {
  try { return await newsRepository.getBySlug(slug); }
  catch (error) {
    if (error instanceof NewsArticleNotFoundError) notFound();
    throw error;
  }
}
