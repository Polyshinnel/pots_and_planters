import Link from 'next/link';
import { Container } from '@mantine/core';
import { getStaticPageMetadata } from '@/lib/page-seo.server';
import { newsRepository } from '@/lib/news/repository';
import { NewsCard } from '@/components/news/NewsCard';
import { NewsContact } from '@/components/news/NewsContact';
import pageStyles from '@/components/catalog/Catalog.module.css';
import styles from '@/components/news/News.module.css';

export const generateMetadata = () => getStaticPageMetadata('news');

export default async function NewsPage() {
  const articles = await newsRepository.getPublished();
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><span aria-current="page">News</span></li>
    </ol></nav>
    <header className={styles.hero}>
      <p className={styles.eyebrow}>FROM THE POTS &amp; PLANTERS ARCHIVE</p>
      <h1>News &amp; Insights</h1>
      <p>Project announcements, company milestones and exhibitions from 2023–2025. Explore stories of planters and greenery in hospitality, design and commercial spaces.</p>
    </header>
    <div className={styles.grid}>{articles.map((article, index) => <NewsCard key={article.id} article={article} priority={index === 0} />)}</div>
    <NewsContact />
  </Container>;
}
