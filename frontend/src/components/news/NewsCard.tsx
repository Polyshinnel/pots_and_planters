import Image from 'next/image';
import Link from 'next/link';
import type { NewsArticle } from '@/types/news';
import { formatNewsDate } from '@/lib/news/repository';
import styles from './News.module.css';

export function NewsCard({ article, priority = false }: { article: NewsArticle; priority?: boolean }) {
  return <article className={styles.card}>
    <Link className={styles.cardLink} href={`/news/${article.slug}`} aria-labelledby={`title-${article.id}`}>
      <div className={styles.cardImage}>
        <Image src={article.coverImage} alt={article.coverAlt} fill preload={priority}
          sizes="(max-width: 575px) calc(100vw - 32px), (max-width: 991px) 46vw, (max-width: 1420px) 30vw, 440px" />
      </div>
      <div className={styles.cardCopy}>
        <div className={styles.meta}><span>{article.category}</span><time dateTime={article.publishedAt}>{formatNewsDate(article.publishedAt)}</time></div>
        <h2 id={`title-${article.id}`}>{article.title}</h2>
        <p>{article.excerpt}</p>
        <span className={styles.readMore}>Read article <span aria-hidden="true">↗</span></span>
      </div>
    </Link>
  </article>;
}
