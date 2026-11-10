'use client';

import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@mantine/core';
import { IconBrandWhatsapp, IconMinus, IconPlus, IconShoppingBag } from '@tabler/icons-react';
import { useWhatsAppUrl } from '@/components/layout/ContactsProvider';
import { MAX_ENQUIRY_QUANTITY, validQuantity } from '@/lib/enquiry/domain';
import { productWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import { ProductEnquiryDialog } from '@/components/forms/ProductEnquiryForm';
import type { ProductEnquiryContext } from '@/lib/forms/contracts';
import styles from './Product.module.css';

export function ProductActions({ product }: { product: ProductEnquiryContext & { slug: string; categoryId: string } }) {
  const pathname = usePathname();
  const whatsappBaseUrl = useWhatsAppUrl();
  const [quoteOpened, setQuoteOpened] = useState(false);
  const [quantity, setQuantity] = useState('1');
  const [origin, setOrigin] = useState<string | null>(null);
  useEffect(() => { setOrigin(window.location.origin); }, []);
  const value = Number(quantity);
  const valid = quantity.trim() !== '' && validQuantity(value);
  const whatsappUrl = origin ? productWhatsAppUrl(whatsappBaseUrl, product, valid ? value : 1, origin) : null;
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
      <Button leftSection={<IconShoppingBag size={19} aria-hidden="true" />} disabled aria-describedby="product-enquiry-unavailable">Add to Enquiry</Button>
      <Button variant="outline" disabled={!valid} onClick={() => setQuoteOpened(true)}>Request a Quote</Button>
    </div>
    <p id="product-enquiry-unavailable" className={styles.note}>Adding products to your enquiry is temporarily unavailable. Request a Quote opens a single-product form. Online submission is not available yet.</p>
    {whatsappUrl
      ? <Button component="a" href={whatsappUrl} target="_blank" rel="noopener noreferrer" variant="subtle"
          aria-disabled={!valid} data-disabled={!valid || undefined} onClick={(event) => { if (!valid) event.preventDefault(); }}
          leftSection={<IconBrandWhatsapp size={20} aria-hidden="true" />}>Enquire on WhatsApp</Button>
      : <Link href="/contacts#project-enquiry">Discuss Your Project</Link>}
  </div><ProductEnquiryDialog opened={quoteOpened} onClose={() => setQuoteOpened(false)} product={product} quantity={quantity} source={{ pagePath: pathname, placement: 'product-detail' }} /></>;
}
