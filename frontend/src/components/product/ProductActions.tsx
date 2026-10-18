'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@mantine/core';
import { IconBrandWhatsapp, IconMinus, IconPlus, IconShoppingBag } from '@tabler/icons-react';
import { siteConfig } from '@/config/site';
import { ENQUIRY_PATH, MAX_ENQUIRY_QUANTITY, validQuantity } from '@/lib/enquiry/domain';
import { enquiryStore, useEnquiry } from '@/lib/enquiry/store';
import { productWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import styles from './Product.module.css';

export function ProductActions({ product }: { product: { id: string; sku: string; slug: string; name: string; categoryId: string } }) {
  const router = useRouter();
  const { hydrated } = useEnquiry();
  const [quantity, setQuantity] = useState('1');
  const [feedback, setFeedback] = useState('');
  const [added, setAdded] = useState(false);
  const [origin, setOrigin] = useState<string | null>(null);
  useEffect(() => { setOrigin(window.location.origin); }, []);
  const value = Number(quantity);
  const valid = quantity.trim() !== '' && validQuantity(value);
  const whatsappUrl = origin ? productWhatsAppUrl(siteConfig.whatsappUrl, product, valid ? value : 1, origin) : null;
  function add(quote: boolean) {
    if (!valid) return;
    try {
      const { persistent } = enquiryStore.addItem(product.id, product.sku, value);
      setAdded(true);
      setFeedback(`${value} × ${product.name} (Model: ${product.sku}) added to your enquiry.${persistent ? '' : ' Browser storage is unavailable; keep this tab open to retain your selection.'}`);
      if (quote) router.push(ENQUIRY_PATH);
    } catch (error) { setFeedback(error instanceof Error ? error.message : 'Unable to add this model. Please try again.'); }
  }

  return <div className={styles.actions}>
    <label className={styles.quantityLabel} htmlFor="product-quantity">Quantity</label>
    <div className={styles.quantity}>
      <button type="button" aria-label="Decrease quantity" disabled={!valid || value <= 1} onClick={() => setQuantity(String(value - 1))}><IconMinus size={18} aria-hidden="true" /></button>
      <input id="product-quantity" type="number" inputMode="numeric" min={1} max={MAX_ENQUIRY_QUANTITY} step={1}
        value={quantity} aria-invalid={!valid} aria-describedby={!valid ? 'quantity-error' : undefined} onChange={(event) => setQuantity(event.target.value)} />
      <button type="button" aria-label="Increase quantity" disabled={!valid || value >= MAX_ENQUIRY_QUANTITY} onClick={() => setQuantity(String(value + 1))}><IconPlus size={18} aria-hidden="true" /></button>
    </div>
    {!valid && <p id="quantity-error" className={styles.error}>Enter a whole number from 1 to {MAX_ENQUIRY_QUANTITY.toLocaleString('en-US')}.</p>}
    <div className={styles.buttons}>
      <Button leftSection={<IconShoppingBag size={19} aria-hidden="true" />} disabled={!hydrated || !valid} onClick={() => add(false)}>Add to Enquiry</Button>
      <Button variant="outline" disabled={!hydrated || !valid} onClick={() => add(true)}>Request a Quote</Button>
    </div>
    <p className={styles.note}>Your selection is saved in this browser. Request a Quote opens an enquiry preview; online submission is not available yet.</p>
    <div role="status" aria-live="polite" aria-atomic="true" className={styles.feedback}>{feedback}</div>
    {added && <Link href={ENQUIRY_PATH}>View enquiry basket →</Link>}
    {whatsappUrl && <Button component="a" href={whatsappUrl} target="_blank" rel="noopener noreferrer" variant="subtle"
      aria-disabled={!valid} data-disabled={!valid || undefined} onClick={(event) => { if (!valid) event.preventDefault(); }}
      leftSection={<IconBrandWhatsapp size={20} aria-hidden="true" />}>Enquire on WhatsApp</Button>}
  </div>;
}
