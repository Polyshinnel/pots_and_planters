'use client';

import Link from 'next/link';
import { useEffect, useRef } from 'react';
import { Button } from '@mantine/core';
import { Fancybox } from '@fancyapps/ui/dist/fancybox/';
import { IconMinus, IconPlus, IconTrash } from '@tabler/icons-react';
import { enquiryStore, useEnquiry } from '@/lib/enquiry/store';
import { MAX_ENQUIRY_QUANTITY } from '@/lib/enquiry/domain';
import { formatPrice } from '@/lib/format-price';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import styles from './Enquiry.module.css';

export function EnquiryEmpty({ onNavigate }: { onNavigate?: () => void }) {
  return <div className={styles.empty}><p>Your enquiry is empty</p><Button component={Link} href="/shop" onClick={onNavigate}>Explore Products</Button></div>;
}
export function EnquiryItems({ onNavigate, compact = false }: { onNavigate?: () => void; compact?: boolean }) {
  const { lines } = useEnquiry();
  const root = useRef<HTMLUListElement>(null);
  useEffect(() => {
    const container = root.current;
    Fancybox.bind(container, '[data-fancybox="enquiry-images"]', { placeFocusBack: true, idle: false });
    return () => { Fancybox.unbind(container); Fancybox.getInstance()?.close(); };
  }, []);
  return <ul ref={root} className={`${styles.items} ${compact ? styles.compactItems : ''}`}>{lines.map((line) => <li key={line.lineKey} className={styles.item}>
    <div className={styles.thumbnail}>{line.imageUrl
      // Catalogue images use the same local assets as the product page.
      ? <a href={line.imageUrl} data-fancybox="enquiry-images" data-caption={line.name} aria-label={`Enlarge image of ${line.name}`}><img src={line.imageUrl} alt="" width={96} height={96} loading="lazy" /></a> : <span>No image</span>}</div>
    <div className={styles.itemBody}>
      {line.href ? <Link href={line.href} onClick={onNavigate} className={styles.name}>{line.name}</Link> : <strong>{line.name}</strong>}
      <p className={styles.detail}>SKU: {line.sku}</p>
      {line.attributes.length > 0 && <p className={styles.detail}>{line.attributes.map((attribute) => attribute.value).join(' · ')}</p>}
      {!line.available && <p className={styles.detail}>This model needs reconfirmation.</p>}
      <p className={styles.price}>{formatPrice(line.unitPriceAed)}{line.unitPriceAed !== null && ' each'}</p>
      <div className={styles.controls}>
        <div className={styles.stepper} role="group" aria-label={`Quantity for ${line.name}, SKU ${line.sku}`}>
          <button type="button" aria-label={`Decrease quantity for ${line.name}`} disabled={line.quantity <= 1} onClick={() => enquiryStore.decrement(line.sku)}><IconMinus size={16} aria-hidden="true" /></button>
          <span aria-live="polite" aria-atomic="true">{line.quantity}</span>
          <button type="button" aria-label={`Increase quantity for ${line.name}`} disabled={line.quantity >= MAX_ENQUIRY_QUANTITY} onClick={() => enquiryStore.increment(line.sku)}><IconPlus size={16} aria-hidden="true" /></button>
        </div>
        <button type="button" className={styles.remove} aria-label={`Remove ${line.name}`} onClick={() => enquiryStore.removeItem(line.sku)}><IconTrash size={18} aria-hidden="true" /></button>
      </div>
      {line.unitPriceAed !== null && <p className={compact ? styles.itemTotal : styles.detail}>Total for this item: {formatPrice(line.unitPriceAed * line.quantity)}</p>}
    </div>
  </li>)}</ul>;
}
export function EnquiryTotals() {
  const { hasUnpricedItems, knownPriceSubtotalAed, lines } = useEnquiry();
  const hasKnownPrice = lines.some((line) => line.unitPriceAed !== null);
  return <div className={styles.totals}>
    {hasKnownPrice && <p><strong>{hasUnpricedItems ? 'Known-price subtotal' : 'Indicative product subtotal'}: {formatPrice(knownPriceSubtotalAed)}</strong></p>}
    <p className={styles.detail}>For reference only. Final pricing, availability, delivery and project terms will be confirmed in your quotation.</p>
  </div>;
}
export function EnquiryStorageNotice() {
  const { persistent } = useEnquiry();
  return !persistent && <p role="status">Browser storage is unavailable. Keep this tab open to retain your selection.</p>;
}
