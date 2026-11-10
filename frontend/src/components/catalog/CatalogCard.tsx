import Link from 'next/link';
import type { Product } from '@/types/catalog';
import { HomeImage } from '@/components/home/HomeImage';
import { ProductCardDetails } from './ProductCardDetails';
import styles from './Catalog.module.css';

export function CatalogCard({ product }: { product: Product }) {
  return <article className={styles.card}>
    <Link href={product.canonicalPath ?? `/shop/${product.primaryCategorySlug ?? 'outdoor-pots'}/${encodeURIComponent(product.slug)}`} className={styles.cardLink}>
      <div className={styles.image}><HomeImage image={product.image ? { src: product.image, alt: product.imageAlt ?? product.name } : null} sizes="(min-width: 1440px) 340px, (min-width: 992px) 25vw, (min-width: 360px) 46vw, 90vw" /></div>
      <ProductCardDetails heading="h2" sku={product.sku} name={product.name} specifications={product.specifications} specificationsText={product.cardSpecificationsText ?? product.cardSpecifications?.map((specification) => specification.displayText).join(' · ')} price={product.priceAed} sample={product.sample} classNames={{ content: styles.cardContent, sku: styles.sku, name: styles.cardName, specifications: styles.specifications, footer: styles.cardFooter, price: styles.price, view: styles.view }} />
    </Link>
  </article>;
}
