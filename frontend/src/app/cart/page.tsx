import Link from 'next/link';
import { Container } from '@mantine/core';
import { IconChecks, IconCreditCardOff } from '@tabler/icons-react';
import { EnquiryCheckout } from '@/components/enquiry/EnquiryCheckout';
import styles from '@/components/product/Product.module.css';

export const metadata = { title: 'Request a Quotation | Pots & Planters', robots: { index: false, follow: true } };
export default function EnquiryPage() {
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol><li><Link href="/">Home</Link></li><li aria-current="page">Request a Quotation</li></ol></nav>
    <h1>Request a Quotation</h1>
    <p className={styles.intro}>Planning a residential, hospitality or commercial project? Review your selected products and send us your requirements. Our team will confirm availability, discuss suitable options and prepare a personalised quotation.</p>
    <ul className={styles.benefits} aria-label="Quotation benefits">
      <li><IconCreditCardOff size={18} aria-hidden="true" /> No payment required</li>
      <li><IconChecks size={18} aria-hidden="true" /> Personalised quotation</li>
    </ul>
    <EnquiryCheckout />
  </Container>;
}
