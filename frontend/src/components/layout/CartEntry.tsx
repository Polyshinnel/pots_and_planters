'use client';

import Link from 'next/link';
import { IconShoppingCart } from '@tabler/icons-react';
import { useEnquiry } from '@/lib/enquiry/store';
import { ENQUIRY_PATH } from '@/lib/enquiry/domain';
import styles from './Layout.module.css';

export function CartEntry() {
  const { totalQuantity } = useEnquiry();
  return <Link href={ENQUIRY_PATH} className={`${styles.headerIconButton} ${styles.cartEntry}`} title="Enquiry basket" aria-label={`Enquiry basket, ${totalQuantity} items`}>
    <IconShoppingCart size={22} stroke={1.25} aria-hidden="true" />
    <span className={styles.cartCount} aria-hidden="true">{totalQuantity > 99 ? '99+' : totalQuantity}</span>
  </Link>;
}
