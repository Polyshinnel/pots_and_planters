import Link from 'next/link';
import type { Product } from '@/types/catalog';
import { ProductCardDetails } from '@/components/catalog/ProductCardDetails';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import styles from './Home.module.css';

export function ProductCard({ product }: { product: Product }) {
  return <article className={styles.productCard}>
    <Link href={product.canonicalPath ?? `/shop/${product.primaryCategorySlug ?? 'outdoor-pots'}/${encodeURIComponent(product.slug)}`} className={styles.productLink}>
      <div className={styles.productImage}><HomeImage image={product.image ? { src: product.image, alt: product.imageAlt ?? product.name } : null} sizes={homeImageSizes.product} objectFit="cover" /></div>
      <ProductCardDetails heading="h3" sku={product.sku} name={product.name} specifications={product.specifications} specificationsText={product.cardSpecificationsText ?? product.cardSpecifications?.map((specification) => specification.displayText).join(' · ')} price={product.priceAed} classNames={{ content: styles.productContent, sku: styles.sku, name: styles.cardName, specifications: styles.specifications, footer: styles.cardFooter, price: styles.price, view: styles.textLink }} />
    </Link>
  </article>;
}
