import Link from 'next/link';
import type { ProductPreview } from '@/types/home';
import { ProductCardDetails } from '@/components/catalog/ProductCardDetails';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import styles from './Home.module.css';

export function ProductCard({ product }: { product: ProductPreview }) {
  return <article className={styles.productCard}>
    <Link href={`/product/${product.slug}`} className={styles.productLink}>
      <div className={styles.productImage}><HomeImage image={product.image} sizes={homeImageSizes.product} objectFit="cover" /></div>
      <ProductCardDetails heading="h3" sku={product.sku} name={product.name} specifications={product.specifications} price={product.price?.amount ?? null} classNames={{ content: styles.productContent, sku: styles.sku, name: styles.cardName, specifications: styles.specifications, footer: styles.cardFooter, price: styles.price, view: styles.textLink }} />
    </Link>
  </article>;
}
