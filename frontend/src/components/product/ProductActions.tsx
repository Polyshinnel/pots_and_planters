'use client';

import { useEffect, useRef, useState } from 'react';
import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@mantine/core';
import { IconBrandWhatsapp, IconMinus, IconPlus, IconShoppingBag } from '@tabler/icons-react';
import { useWhatsAppUrl } from '@/components/layout/ContactsProvider';
import { MAX_ENQUIRY_QUANTITY, validQuantity } from '@/lib/enquiry/domain';
import { productWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import { ProductEnquiryDialog } from '@/components/forms/ProductEnquiryForm';
import { enquiryStoreV2 } from '@/lib/enquiry/store-v2';
import { resolveProducts } from '@/lib/enquiry/product-resolve.client';
import type { ProductEnquiryContext } from '@/lib/forms/contracts';
import styles from './Product.module.css';

export function ProductActions({ product }: { product: ProductEnquiryContext & { slug: string; categoryId: string } }) {
  const pathname = usePathname();
  const whatsappBaseUrl = useWhatsAppUrl();
  const [quoteOpened, setQuoteOpened] = useState(false);
  const [quantity, setQuantity] = useState('1');
  const [origin, setOrigin] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const [feedback, setFeedback] = useState('');
  const addInProgress = useRef(false);
  const productEnquiriesEnabled = process.env.NEXT_PUBLIC_PRODUCT_ENQUIRIES_ENABLED === 'true';
  useEffect(() => { setOrigin(window.location.origin); }, []);
  const value = Number(quantity);
  const valid = quantity.trim() !== '' && validQuantity(value);
  const whatsappUrl = origin ? productWhatsAppUrl(whatsappBaseUrl, product, valid ? value : 1, origin) : null;
  const addToEnquiry = async () => {
    if (addInProgress.current || !valid || !productEnquiriesEnabled) return;
    addInProgress.current = true;
    setAdding(true);
    setFeedback('');
    try {
      const lines = await resolveProducts([{ productId: product.id, quantity: value }]);
      if (lines[0]?.status !== 'available') {
        setFeedback('This product is no longer available and was not added.');
        return;
      }
      const result = enquiryStoreV2.add(product.id, value);
      if (!result.ok) {
        setFeedback(result.code === 'line_limit' ? 'Your enquiry already contains the maximum of 100 products.'
          : result.code === 'quantity_limit' ? 'The maximum quantity for this product is 999,999.'
            : result.code === 'corrupt' ? 'Saved enquiry data needs recovery before it can be changed.'
              : 'Your enquiry could not be saved. Check browser storage and try again.');
        return;
      }
      const updated = result.state.items.find((item) => item.productId === product.id)?.quantity ?? value;
      setFeedback(updated > value ? `Quantity updated to ${updated} in your enquiry.` : `${product.name} added to your enquiry.`);
    } catch {
      setFeedback('We could not verify this product right now. It was not added; please try again.');
    } finally {
      addInProgress.current = false;
      setAdding(false);
    }
  };
  return <><div className={styles.actions}>
    <label className={styles.quantityLabel} htmlFor="product-quantity">Quantity</label>
    <div className={styles.quantity}>
      <button type="button" aria-label="Decrease quantity" disabled={!valid || value <= 1} onClick={() => setQuantity(String(value - 1))}><IconMinus size={18} aria-hidden="true" /></button>
      <input id="product-quantity" type="number" inputMode="numeric" min={1} max={MAX_ENQUIRY_QUANTITY} step={1}
        value={quantity} aria-invalid={!valid} aria-describedby={!valid ? 'quantity-error' : undefined} onChange={(event) => setQuantity(event.target.value)} />
      <button type="button" aria-label="Increase quantity" disabled={!valid || value >= MAX_ENQUIRY_QUANTITY} onClick={() => setQuantity(String(value + 1))}><IconPlus size={18} aria-hidden="true" /></button>
    </div>
    {!valid && <p id="quantity-error" className={styles.error}>Enter a whole number from 1 to {MAX_ENQUIRY_QUANTITY.toLocaleString('en-US')}.</p>}
    <div className={styles.buttons}>
      <Button leftSection={<IconShoppingBag size={19} aria-hidden="true" />} disabled={!productEnquiriesEnabled || !valid || adding} loading={adding} onClick={addToEnquiry} aria-describedby={!productEnquiriesEnabled ? 'product-enquiry-unavailable' : undefined}>Add to Enquiry</Button>
      <Button variant="outline" disabled={!valid} onClick={() => setQuoteOpened(true)}>Request a Quote</Button>
    </div>
    {!productEnquiriesEnabled && <p id="product-enquiry-unavailable" className={styles.note}>Adding products to your enquiry is temporarily unavailable. Request a Quote opens a single-product form. Online submission is not available yet.</p>}
    {feedback && <p role={feedback.includes('added') || feedback.includes('updated') ? 'status' : 'alert'} className={styles.note}>{feedback}</p>}
    {whatsappUrl
      ? <Button component="a" href={whatsappUrl} target="_blank" rel="noopener noreferrer" variant="subtle"
          aria-disabled={!valid} data-disabled={!valid || undefined} onClick={(event) => { if (!valid) event.preventDefault(); }}
          leftSection={<IconBrandWhatsapp size={20} aria-hidden="true" />}>Enquire on WhatsApp</Button>
      : <Link href="/contacts#project-enquiry">Discuss Your Project</Link>}
  </div><ProductEnquiryDialog opened={quoteOpened} onClose={() => setQuoteOpened(false)} product={product} quantity={quantity} source={{ pagePath: pathname, placement: 'product-detail' }} /></>;
}
