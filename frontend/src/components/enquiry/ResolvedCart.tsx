'use client';

import { useEffect, useMemo, useState } from 'react';
import { Button } from '@mantine/core';
import { useEnquiryV2, enquiryStoreV2 } from '@/lib/enquiry/store-v2';
import { formatResolvedPrice, resolveProducts, type ResolvedLine } from '@/lib/enquiry/product-resolve.client';
import styles from './Enquiry.module.css';

export function ResolvedCart() {
  const state = useEnquiryV2();
  const [resolved, setResolved] = useState<ResolvedLine[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [removeError, setRemoveError] = useState('');
  const [retry, setRetry] = useState(0);
  const identity = useMemo(() => state.items.map(({ productId }) => productId).join('|'), [state.items]);
  useEffect(() => {
    if (!state.hydrated || state.v2Corrupt || !state.items.length) { setResolved(null); setFailed(false); return; }
    let active = true;
    setResolved(null); setFailed(false);
    resolveProducts(state.items).then((lines) => { if (active) setResolved(lines); }).catch(() => { if (active) setFailed(true); });
    return () => { active = false; };
  }, [state.hydrated, state.v2Corrupt, identity, retry]);

  if (!state.hydrated) return <p role="status">Loading your saved enquiry items…</p>;
  const hasProblem = state.status === 'corrupt' || state.status === 'storage_unavailable';
  const empty = !state.items.length && !state.legacyItems.length;
  return <>
    {state.status === 'storage_unavailable' && <p role="alert">Browser storage is unavailable. Your saved enquiry state cannot be reliably read or saved.</p>}
    {state.status === 'corrupt' && <p role="alert">Some saved enquiry data is damaged or from an unsupported version. It has been preserved and is not being shown as an empty enquiry.</p>}
    {state.items.length > 0 && <section aria-labelledby="current-items"><h2 id="current-items">Current catalogue items</h2>
      {state.v2Corrupt ? <p role="status">Current catalogue item data needs recovery before it can be displayed.</p> : failed
        ? <div role="alert"><p>We couldn't load your enquiry items. Please try again.</p><Button onClick={() => setRetry((value) => value + 1)}>Retry</Button></div>
        : !resolved ? <p role="status">Loading product details…</p> : <ul className={styles.items}>{resolved.map((line) => line.status === 'unavailable'
          ? <li key={line.item.productId} className={styles.item}><div className={styles.thumbnail}><span>No image</span></div><div className={styles.itemBody}><strong>This product is no longer available</strong><p className={styles.detail}>Quantity: {line.item.quantity}</p></div></li>
          : <li key={line.item.productId} className={styles.item}>
            <div className={styles.thumbnail}>{line.product.cardImage ? <img src={line.product.cardImage.url} alt={line.product.cardImage.alt} width={96} height={96} loading="lazy" /> : <span>No image</span>}</div>
            <div className={styles.itemBody}><strong>{line.product.name}</strong><p className={styles.detail}>Brand: {line.product.brand.name}</p><p className={styles.detail}>SKU: {line.product.sku}</p>
              {line.product.cardSpecifications.length > 0 && <p className={styles.detail}>{line.product.cardSpecifications.map((specification) => specification.displayText).join(' · ')}</p>}
              <p className={styles.price}>{formatResolvedPrice(line.product.priceAed)}</p><p className={styles.detail}>Quantity: {line.item.quantity}</p>
            </div>
          </li>)}</ul>}
    </section>}
    {state.legacyItems.length > 0 && <section aria-labelledby="legacy-items"><h2 id="legacy-items">Older saved items</h2><p>These items have not been matched to the current catalogue.</p><ul className={styles.items}>{state.legacyItems.map((item) => <li key={item.sourceIndex} className={styles.item}>
      <div className={styles.itemBody}><strong>SKU: {item.sku}</strong><p className={styles.detail}>Quantity: {item.quantity}</p><p className={styles.detail}>This saved item is from an older enquiry list and needs to be added again from the current catalogue.</p>
        <Button variant="subtle" color="gray" disabled={state.v1Corrupt || !state.persistent || hasProblem} onClick={() => { setRemoveError(''); const result = enquiryStoreV2.removeLegacyAt(item.sourceIndex); if (!result.ok) setRemoveError(result.code === 'storage_unavailable' ? 'This saved item could not be removed because browser storage could not save the change.' : 'This saved item could not be safely removed because the saved data needs recovery.'); }}>Remove this saved item</Button>
        {removeError && <p role="alert">{removeError}</p>}
      </div></li>)}</ul></section>}
    {empty && !hasProblem && <div className={styles.empty}><p>Your enquiry is empty</p></div>}
    {!state.items.length && !state.legacyItems.length && hasProblem && <p role="status">Saved items cannot be safely displayed.</p>}
  </>;
}
