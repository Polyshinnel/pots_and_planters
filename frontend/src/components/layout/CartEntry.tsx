import { IconShoppingCart } from '@tabler/icons-react';
import { cartPreview } from '@/config/site';
import styles from './Layout.module.css';

export function CartEntry() {
  return <button type="button" className={`${styles.headerIconButton} ${styles.cartEntry}`} disabled title="Cart preview" aria-label={`Cart, ${cartPreview.itemCount} items. Preview only`}>
    <IconShoppingCart size={22} stroke={1.25} aria-hidden="true" />
    <span className={styles.cartCount} aria-hidden="true">{cartPreview.itemCount > 99 ? '99+' : cartPreview.itemCount}</span>
  </button>;
}
