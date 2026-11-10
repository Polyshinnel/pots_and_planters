'use client';

import { useEffect, useMemo, useState } from 'react';
import { Button } from '@mantine/core';
import Link from 'next/link';
import { IconMinus, IconPlus, IconTrash } from '@tabler/icons-react';
import { useEnquiryV2, enquiryStoreV2 } from '@/lib/enquiry/store-v2';
import { formatResolvedPrice, resolveProducts, type ResolvedLine } from '@/lib/enquiry/product-resolve.client';
import styles from './Enquiry.module.css';

function multiplyPrice(value: string | null, quantity: number): string | null {
  if (value === null) return null;
  const [whole, fraction = ''] = value.split('.');
  const product = (BigInt(`${whole}${fraction}`) * BigInt(quantity)).toString().padStart(fraction.length + 1, '0');
  return fraction ? `${product.slice(0, -fraction.length)}.${product.slice(-fraction.length)}` : product;
}
function sumKnownPrices(lines: readonly ResolvedLine[]): { value: string; hasUnpriced: boolean; hasPriced: boolean } {
  const priced = lines.flatMap((line) => line.status === 'available' && line.product.priceAed !== null ? [{ value: line.product.priceAed, quantity: line.item.quantity }] : []);
  const scale = Math.max(0, ...priced.map(({ value }) => value.split('.')[1]?.length ?? 0));
  const total = priced.reduce((sum, { value, quantity }) => {
    const [whole, fraction = ''] = value.split('.');
    return sum + BigInt(`${whole}${fraction.padEnd(scale, '0')}`) * BigInt(quantity);
  }, BigInt(0)).toString().padStart(scale + 1, '0');
  const formatted = scale ? `${total.slice(0, -scale)}.${total.slice(-scale)}` : total;
  return { value: formatted, hasUnpriced: priced.length !== lines.length, hasPriced: priced.length > 0 };
}

export function ResolvedCart() {
  const state = useEnquiryV2();
  const [resolved, setResolved] = useState<ResolvedLine[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [removeError, setRemoveError] = useState('');
  const [editError, setEditError] = useState('');
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
  const canEdit = process.env.NEXT_PUBLIC_PRODUCT_ENQUIRIES_ENABLED === 'true' && state.hydrated && state.persistent && !hasProblem;
  const edit = (operation: () => ReturnType<typeof enquiryStoreV2.setQuantity>) => {
    setEditError('');
    const result = operation();
    if (!result.ok) setEditError(result.code === 'quantity_limit' ? 'The maximum quantity is 999,999.' : result.code === 'corrupt' ? 'Saved enquiry data needs recovery before it can be changed.' : 'This change could not be saved to browser storage.');
  };
  const remove = (productId: string) => {
    setEditError('');
    const result = enquiryStoreV2.remove(productId);
    if (!result.ok) setEditError(result.code === 'corrupt' ? 'Saved enquiry data needs recovery before it can be changed.' : 'This item could not be removed from browser storage.');
  };
  const empty = !state.items.length && !state.legacyItems.length;
  const totals = resolved ? sumKnownPrices(resolved) : null;
  return <>
    {state.status === 'storage_unavailable' && <p role="alert">Browser storage is unavailable. Your saved enquiry state cannot be reliably read or saved.</p>}
    {state.status === 'corrupt' && <p role="alert">Some saved enquiry data is damaged or from an unsupported version. It has been preserved and is not being shown as an empty enquiry.</p>}
    {state.items.length > 0 && <section aria-label="Current catalogue items">
      {state.v2Corrupt ? <p role="status">Current catalogue item data needs recovery before it can be displayed.</p> : failed
        ? <div role="alert"><p>We couldn't load your enquiry items. Please try again.</p><Button onClick={() => setRetry((value) => value + 1)}>Retry</Button></div>
        : !resolved ? <p role="status">Loading product details…</p> : <ul className={styles.items}>{resolved.map((line) => {
          const quantity = state.items.find((item) => item.productId === line.item.productId)?.quantity ?? line.item.quantity;
          const controls = <div className={styles.controls}>
            {line.status === 'available' && <div className={styles.stepper} aria-label={`Quantity for ${line.product.name}`}>
              <button type="button" aria-label="Decrease quantity" disabled={!canEdit || quantity <= 1} onClick={() => edit(() => enquiryStoreV2.setQuantity(line.item.productId, quantity - 1))}><IconMinus size={18} aria-hidden="true" /></button>
              <span aria-live="polite">{quantity}</span>
              <button type="button" aria-label="Increase quantity" disabled={!canEdit || quantity >= 999999} onClick={() => edit(() => enquiryStoreV2.setQuantity(line.item.productId, quantity + 1))}><IconPlus size={18} aria-hidden="true" /></button>
            </div>}
            {line.status === 'unavailable' && <p className={styles.detail}>Quantity: {quantity}</p>}
            <Button variant="subtle" color="gray" disabled={!canEdit} leftSection={<IconTrash size={17} aria-hidden="true" />} onClick={() => remove(line.item.productId)}>Remove</Button>
          </div>;
          return line.status === 'unavailable'
          ? <li key={line.item.productId} className={styles.item}><div className={styles.thumbnail}><span>No image</span></div><div className={styles.itemBody}><strong>This product is no longer available</strong>{controls}</div></li>
          : <li key={line.item.productId} className={styles.item}>
            <div className={styles.thumbnail}>{line.product.cardImage ? <img src={line.product.cardImage.url} alt={line.product.cardImage.alt} width={96} height={96} loading="lazy" /> : <span>No image</span>}</div>
            <div className={styles.itemBody}><Link href={line.product.canonicalPath} className={styles.name}>{line.product.name}</Link><p className={styles.detail}>SKU: {line.product.sku}</p>
              {line.product.cardSpecifications.length > 0 && <p className={styles.detail}>{line.product.cardSpecifications.map((specification) => specification.displayText).join(' · ')}</p>}
              <p className={styles.price}>{formatResolvedPrice(line.product.priceAed)}{line.product.priceAed !== null && ' each'}</p>{controls}
              {line.product.priceAed !== null && <p className={styles.itemTotal}>Total for this item: {formatResolvedPrice(multiplyPrice(line.product.priceAed, quantity))}</p>}
            </div>
          </li>;
        })}</ul>}
      {totals && <div className={styles.totals}>
        {totals.hasPriced && <p><strong>{totals.hasUnpriced ? 'Known-price subtotal' : 'Indicative product subtotal'}: {formatResolvedPrice(totals.value)}</strong></p>}
        <p className={styles.detail}>For reference only. Final pricing, availability, delivery and project terms will be confirmed in your quotation.</p>
      </div>}
      {editError && <p role="alert">{editError}</p>}
    </section>}
    {state.legacyItems.length > 0 && <section aria-labelledby="legacy-items"><h2 id="legacy-items">Older saved items</h2><p>These items have not been matched to the current catalogue.</p><ul className={styles.items}>{state.legacyItems.map((item) => <li key={item.sourceIndex} className={styles.item}>
      <div className={styles.itemBody}><strong>SKU: {item.sku}</strong><p className={styles.detail}>Quantity: {item.quantity}</p><p className={styles.detail}>This saved item is from an older enquiry list and needs to be added again from the current catalogue.</p>
        <Button variant="subtle" color="gray" disabled={state.v1Corrupt || !state.persistent || hasProblem} onClick={() => { setRemoveError(''); const result = enquiryStoreV2.removeLegacyAt(item.sourceIndex); if (!result.ok) setRemoveError(result.code === 'storage_unavailable' ? 'This saved item could not be removed because browser storage could not save the change.' : 'This saved item could not be safely removed because the saved data needs recovery.'); }}>Remove this saved item</Button>
        {removeError && <p role="alert">{removeError}</p>}
      </div></li>)}</ul></section>}
    {empty && !hasProblem && <div className={styles.empty}><p>Your enquiry is empty</p><Button component={Link} href="/shop">Explore Products</Button></div>}
    {!state.items.length && !state.legacyItems.length && hasProblem && <p role="status">Saved items cannot be safely displayed.</p>}
  </>;
}
