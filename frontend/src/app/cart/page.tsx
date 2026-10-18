import Link from 'next/link';
import { Container } from '@mantine/core';
import { EnquiryPreview } from '@/components/product/EnquiryPreview';
import styles from '@/components/product/Product.module.css';

export const metadata = { title: 'Enquiry basket | Pots & Planters', robots: { index: false, follow: true } };
export default function EnquiryPage() {
  return <Container className={styles.page}>
    <nav aria-label="Breadcrumb" className={styles.breadcrumb}><ol><li><Link href="/">Home</Link></li><li aria-current="page">Enquiry basket</li></ol></nav>
    <h1>Enquiry basket</h1>
    <EnquiryPreview />
  </Container>;
}
