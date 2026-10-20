import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Container } from '@mantine/core';
import { newsRepository, formatNewsDate } from '@/lib/news/repository';
import { newsMetadata } from '@/lib/news/seo';
import { NewsContact } from '@/components/news/NewsContact';
import pageStyles from '@/components/catalog/Catalog.module.css';
import styles from '@/components/news/News.module.css';

export const dynamicParams = false;
type Props = { params: Promise<{ slug: string }> };

export async function generateStaticParams() {
  return (await newsRepository.getPublished()).map(({ slug }) => ({ slug }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const article = await newsRepository.getBySlug((await params).slug);
  if (!article) notFound();
  return newsMetadata(article);
}

export default async function NewsArticlePage({ params }: Props) {
  const article = await newsRepository.getBySlug((await params).slug);
  if (!article) notFound();
  // Matches the production metadataBase configured in the root layout.
  const canonical = `https://potsandplanters.ae/news/${article.slug}`;
  const jsonLd = {
    '@context': 'https://schema.org', '@type': 'NewsArticle',
    headline: article.title, datePublished: article.publishedAt,
    image: new URL(article.coverImage, canonical).href,
    url: canonical, mainEntityOfPage: { '@type': 'WebPage', '@id': canonical },
    articleSection: article.category,
  };
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><Link href="/news">News</Link></li>
      <li><span aria-current="page">{article.title}</span></li>
    </ol></nav>
    <article className={styles.article}>
      <figure className={styles.cover}><Image src={article.coverImage} alt={article.coverAlt} width={848} height={848} preload sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1200px) 45vw, 50vw" /></figure>
      <div className={styles.articleContent}>
      <header className={styles.articleHeader}>
        <div className={styles.meta}><span>{article.category}</span><time dateTime={article.publishedAt}>{formatNewsDate(article.publishedAt)}</time></div>
        <h1>{article.title}</h1>
        <p className={styles.archiveNote}>From the Pots &amp; Planters archive. The original company announcement below was published on {formatNewsDate(article.publishedAt)}; statements reflect that date.
          {article.id === 'news-5' && ' INDEX Dubai 2025 took place on 27–29 May 2025. This is a past event.'}
          {article.id === 'news-4' && ' This 2024 service announcement does not confirm current service availability. Contact our team for current options.'}
        </p>
      </header>
      <div className={styles.body}>{article.body.map((block, index) => {
        if (block.type === 'heading') return block.level === 2 ? <h2 key={index}>{block.text}</h2> : <h3 key={index}>{block.text}</h3>;
        if (block.type === 'links') return <ul key={index}>{block.links.map((link) => <li key={link.href}><a href={link.href} target="_blank" rel="noopener noreferrer">{link.label}</a></li>)}</ul>;
        return <p key={index}>{block.children.map((part, partIndex) => part.type === 'link'
          ? <a key={partIndex} href={part.href} target="_blank" rel="noopener noreferrer">{part.text}</a>
          : part.text)}</p>;
      })}</div>
      <footer className={styles.articleFooter}><Link className={styles.textLink} href="/news"><span aria-hidden="true">←</span> Back to News</Link></footer>
      </div>
    </article>
    <NewsContact />
    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }} />
  </Container>;
}
