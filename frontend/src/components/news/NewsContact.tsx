import Link from 'next/link';
import styles from './News.module.css';

export function NewsContact() {
  return <section className={styles.closing} aria-labelledby="news-project-title">
    <h2 id="news-project-title">Planning a project of your own?</h2>
    <p>Explore planter and greenery ideas, or contact our team to discuss your requirements.</p>
    <div className={styles.actions}>
      <Link className={styles.textLink} href="/contacts">Discuss Your Project <span aria-hidden="true">↗</span></Link>
      <Link className={styles.textLink} href="/solutions">Explore Solutions <span aria-hidden="true">↗</span></Link>
    </div>
  </section>;
}
