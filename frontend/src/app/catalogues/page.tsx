import { Suspense } from 'react';
import { getStaticPageMetadata } from '@/lib/page-seo.server';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { catalogRepository } from '@/lib/catalog/repository';
import { getCategories } from '@/lib/catalog/categories.server';
import { getProjectContact } from '@/lib/project-contact';
import { CataloguesListing } from '@/components/catalogues/CataloguesListing';
import pageStyles from '@/components/catalog/Catalog.module.css';
import styles from '@/components/catalogues/Catalogues.module.css';

export const generateMetadata = () => getStaticPageMetadata('catalogues');
export const dynamic = 'force-dynamic';

export default async function Catalogues() {
  const [catalogues, categories] = await Promise.all([catalogRepository.getCatalogues(), getCategories()]);
  const contact = getProjectContact('Hello Pots & Planters, I would like help selecting products from your catalogues for a project in the UAE.');
  return <Container className={pageStyles.page}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><span aria-current="page">Catalogues</span></li>
    </ol></nav>
    <header className={pageStyles.heading}>
      <h1>Product Catalogues</h1>
      <p>Explore our product catalogues for outdoor and indoor planters, artificial plants and trees. Find ideas and suitable options for hospitality, commercial and residential projects across the UAE.</p>
    </header>
    <Suspense fallback={<p role="status">Loading catalogues…</p>}>
      <CataloguesListing catalogues={catalogues} categories={categories} />
    </Suspense>
    <section className={styles.project} aria-labelledby="catalogues-project-title">
      <h2 id="catalogues-project-title">Need help selecting products for your project?</h2>
      <p>Discuss sizes, finishes and quantities with our team, and get tailored recommendations for your hospitality, commercial or residential project.</p>
      <a href={contact.href} target={contact.isWhatsApp ? '_blank' : undefined} rel={contact.isWhatsApp ? 'noopener noreferrer' : undefined}>
        {contact.isWhatsApp ? 'Discuss on WhatsApp (opens in a new tab)' : 'Discuss Your Project'}
      </a>
    </section>
  </Container>;
}
