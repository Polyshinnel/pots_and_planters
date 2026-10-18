import Link from 'next/link';
import type { ProductPreview } from '@/types/home';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import styles from './Home.module.css';

export function ProductCard({ product }: { product: ProductPreview }) {
  return <article className={styles.productCard}>
    <Link href={`/product/${product.slug}`} className={styles.productLink}>
      <div className={styles.productImage}><HomeImage image={product.image} sizes={homeImageSizes.product} objectFit="cover" /></div>
      <div className={styles.productContent}>
        {product.sku && <p className={styles.sku}>Model: {product.sku}</p>}
        <h3 title={product.name}>{product.name}</h3>
        <p className={styles.price}>{product.price
          ? new Intl.NumberFormat('en-AE', { style: 'currency', currency: product.price.currency }).format(product.price.amount)
          : 'Price on enquiry'}</p>
        <span className={styles.textLink}>View product <span aria-hidden="true">↗</span></span>
      </div>
    </Link>
  </article>;
}
