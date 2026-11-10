import Link from 'next/link';
import { Container } from '@mantine/core';
import { ResolvedCart } from '@/components/enquiry/ResolvedCart';
import styles from '@/components/product/Product.module.css';

export const metadata = { title: 'Request a Quotation | Pots & Planters', robots: { index: false, follow: true } };
export default function EnquiryPage() {
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol><li><Link href="/">Home</Link></li><li aria-current="page">Request a Quotation</li></ol></nav>
    <h1>Request a Quotation</h1>
    <p className={styles.intro}>Review your saved enquiry items and their current catalogue status.</p>
    <ResolvedCart />
  </Container>;
}
