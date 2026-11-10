'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { Button } from '@mantine/core';
import { IconMinus, IconPlus, IconTrash } from '@tabler/icons-react';
import { enquiryStoreV2, useEnquiryV2 } from '@/lib/enquiry/store-v2';
import { resolveProducts, type ResolvedLine } from '@/lib/enquiry/product-resolve.client';
import styles from './Enquiry.module.css';

export function CartPreviewItems({ opened }: { opened: boolean }) {
  const state = useEnquiryV2();
  const [resolved, setResolved] = useState<ResolvedLine[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);
  const [error, setError] = useState('');
  const identity = useMemo(() => state.items.map(({ productId }) => productId).join('|'), [state.items]);
  const featureEnabled = process.env.NEXT_PUBLIC_PRODUCT_ENQUIRIES_ENABLED === 'true';

  useEffect(() => {
    if (!opened || !state.hydrated || state.v2Corrupt || !state.items.length) {
      if (!state.items.length || state.v2Corrupt) setResolved(null);
      setFailed(false);
      return;
    }
    let active = true;
    setResolved(null);
    setFailed(false);
    resolveProducts(state.items).then((lines) => { if (active) setResolved(lines); }).catch(() => { if (active) setFailed(true); });
    return () => { active = false; };
  }, [opened, state.hydrated, state.v2Corrupt, identity, retry]);

  if (!opened || !state.items.length) return null;
  if (state.v2Corrupt || state.status === 'storage_unavailable') return <p role="alert">Saved enquiry data needs attention before it can be changed.</p>;
  if (failed) return <div role="alert"><p>We couldn't load product details.</p><Button size="compact-sm" variant="subtle" onClick={() => setRetry((value) => value + 1)}>Retry</Button></div>;
  if (!resolved) return <p role="status">Loading product details…</p>;

  const canEdit = featureEnabled && state.persistent && state.hydrated && !state.v1Corrupt && !state.v2Corrupt;
  const edit = (operation: () => ReturnType<typeof enquiryStoreV2.setQuantity>) => {
    setError('');
    const result = operation();
    if (!result.ok) setError(result.code === 'quantity_limit' ? 'The maximum quantity is 999,999.' : 'This change could not be saved to browser storage.');
  };
  const remove = (productId: string) => {
    setError('');
    const result = enquiryStoreV2.remove(productId);
    if (!result.ok) setError('This item could not be removed from browser storage.');
  };

  return <>
    <div className={`${styles.miniScroll} ${styles.compactItems}`}>
      <ul className={styles.items}>{resolved.map((line) => {
        const current = state.items.find((item) => item.productId === line.item.productId);
        if (!current) return null;
        const product = line.status === 'available' ? line.product : null;
        return <li key={line.item.productId} className={styles.item}>
          <div className={styles.thumbnail}>{product?.cardImage
            ? <img src={product.cardImage.url} alt={product.cardImage.alt} width={64} height={64} loading="lazy" />
            : <span>No image</span>}</div>
          <div className={styles.itemBody}>
            {product ? <Link href={product.canonicalPath} className={styles.name}>{product.name}</Link> : <strong>This product is no longer available</strong>}
            {product && <p className={styles.detail}>SKU: {product.sku}</p>}
            <div className={styles.controls}>
              {product
                ? <div className={styles.stepper} role="group" aria-label={`Quantity for ${product.name}`}>
                    <button type="button" aria-label={`Decrease quantity for ${product.name}`} disabled={!canEdit || current.quantity <= 1} onClick={() => edit(() => enquiryStoreV2.setQuantity(current.productId, current.quantity - 1))}><IconMinus size={15} aria-hidden="true" /></button>
                    <span aria-live="polite" aria-atomic="true">{current.quantity}</span>
                    <button type="button" aria-label={`Increase quantity for ${product.name}`} disabled={!canEdit || current.quantity >= 999999} onClick={() => edit(() => enquiryStoreV2.setQuantity(current.productId, current.quantity + 1))}><IconPlus size={15} aria-hidden="true" /></button>
                  </div>
                : <p className={styles.detail}>Quantity: {current.quantity}</p>}
              <button type="button" className={styles.remove} aria-label={`Remove ${product?.name ?? 'unavailable product'}`} disabled={!canEdit} onClick={() => remove(current.productId)}><IconTrash size={17} aria-hidden="true" /></button>
            </div>
          </div>
        </li>;
      })}</ul>
    </div>
    {error && <p role="alert">{error}</p>}
  </>;
}
