import { getStaticPageMetadata } from '@/lib/page-seo.server';
import Link from 'next/link';
import { Container } from '@mantine/core';
import { getContactsResult } from '@/lib/contacts.server';
import { getProjectContact } from '@/lib/project-contact';
import { ContactLinks } from '@/components/contacts/ContactLinks';
import { ContactEnquiryButton } from '@/components/contacts/ContactEnquiryButton';
import { Showrooms } from '@/components/contacts/Showrooms';
import { ContactEnquiryForm } from '@/components/forms/ContactEnquiryForm';
import pageStyles from '@/components/catalog/Catalog.module.css';
import styles from '@/components/contacts/Contacts.module.css';

export const generateMetadata = () => getStaticPageMetadata('contacts');
export const dynamic = 'force-dynamic';

export default async function ContactsPage() {
  const result = await getContactsResult();
  const contacts = result.status === 'success' ? result.data : null;
  const contact = getProjectContact('Hello Pots & Planters, I would like to discuss planters and artificial greenery for my project.', contacts?.whatsappUrl ?? null);
  return <Container className={`${pageStyles.page} ${styles.page}`}>
    <nav aria-label="Breadcrumb" className={pageStyles.breadcrumb}><ol>
      <li><Link href="/">Main</Link></li><li><span aria-current="page">Contacts</span></li>
    </ol></nav>
    <header className={pageStyles.heading}>
      <h1>Contact Us</h1>
      <p>Planning a hospitality, commercial or residential project? Get in touch to discuss planters, artificial greenery, product options and project requirements.</p>
      <ContactEnquiryButton />
    </header>
    <section className={styles.direct} aria-labelledby="direct-contact-title">
      <div><h2 id="direct-contact-title">Get in Touch</h2><p>Call or email our main Dubai showroom to discuss your project.</p></div>
      <div>{contacts ? <>{contacts.primary ? <ContactLinks details={contacts.primary} /> : <p>No primary showroom contact is currently published.</p>}</> : <p role="alert">Contact details are temporarily unavailable. Please try again later.</p>}
        {contact.isWhatsApp
          ? <a className={styles.action} href={contact.href} target="_blank" rel="noopener noreferrer">Discuss on WhatsApp</a>
          : <a className={styles.action} href={contact.href}>Discuss Your Project</a>}
      </div>
    </section>
    {contacts ? contacts.showrooms.length ? <Showrooms showrooms={contacts.showrooms} /> : <p>No showrooms are currently published.</p> : null}
    <section id="project-enquiry" className={styles.enquiry} aria-labelledby="project-enquiry-title" tabIndex={-1}>
      <div><h2 id="project-enquiry-title">Tell Us About Your Project</h2>
        <p>For architects, interior and landscape designers, contractors, hospitality teams and project buyers.</p>
        <p>Share the products, specifications, quantities and project requirements you have in mind so we can explore suitable options.</p>
        <p className={styles.note}>Online submission is not available yet. This form checks your details without sending them. To contact the team now, please call or email a showroom.</p>
      </div>
      <div className={styles.formPanel}><ContactEnquiryForm source={{ pagePath: '/contacts', placement: 'contacts_page' }} /></div>
    </section>
    <section className={styles.closing} aria-labelledby="project-support-title">
      <div><h2 id="project-support-title">Need planters for your spaces?</h2><p>Browse our online collection or explore our PDF catalogues.</p></div>
      <div className={styles.closingActions}>
        <Link className={styles.closingPrimary} href="/catalogues">Catalogues <span aria-hidden="true">↗</span></Link>
        <Link className={styles.closingSecondary} href="/shop">Explore Collection <span aria-hidden="true">↗</span></Link>
      </div>
    </section>
  </Container>;
}
