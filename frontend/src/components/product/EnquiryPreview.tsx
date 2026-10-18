'use client';

import Link from 'next/link';
import { useEnquiry } from '@/lib/enquiry/store';
import styles from './Product.module.css';

/** FE-04 navigation destination. FE-05 will add product revalidation and editing. */
export function EnquiryPreview() {
  const { items, totalQuantity, hydrated, persistent } = useEnquiry();
  return <div className={styles.preview}>
    <p>This is a preview of your saved selection. Online enquiry submission will be available in a later stage. Nothing has been sent.</p>
    {!persistent && <p role="status">Browser storage is unavailable. Keep this tab open to retain your selection.</p>}
    {!hydrated ? <p role="status">Loading your selection…</p> : items.length ? <>
      <p role="status">{totalQuantity} items in your enquiry</p>
      <ul>{items.map((item) => <li key={item.sku}><span>Model: {item.sku}</span><span>Quantity: {item.quantity}</span></li>)}</ul>
      <p>Saved models, specifications and availability need confirmation before a quote.</p>
    </> : <p role="status">Your enquiry basket is empty.</p>}
    <Link href="/shop">Continue browsing →</Link>
  </div>;
}
