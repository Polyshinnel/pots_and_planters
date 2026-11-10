'use client';

import { Button, Textarea, TextInput } from '@mantine/core';
import Link from 'next/link';
import { IconBrandWhatsapp } from '@tabler/icons-react';
import { useWhatsAppUrl } from '@/components/layout/ContactsProvider';
import { enquiryWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import { ResolvedCart } from './ResolvedCart';
import styles from './Enquiry.module.css';

export function EnquiryCartLayout() {
  const whatsappBaseUrl = useWhatsAppUrl();
  const whatsappUrl = enquiryWhatsAppUrl(whatsappBaseUrl, "Hello! I'd like to discuss planters for my project.");
  return <div className={styles.checkout}>
    <section className={styles.review} aria-labelledby="enquiry-products">
      <h2 id="enquiry-products">Your selection</h2>
      <ResolvedCart />
      <aside className={styles.consultation} aria-labelledby="project-help">
        <h3 id="project-help">Need help with your project?</h3>
        <p>Not sure about sizes, finishes or quantities? Our team can help you select planters for your space, discuss alternatives and prepare a quotation for your requirements.</p>
        {whatsappUrl
          ? <Button component="a" href={whatsappUrl} target="_blank" rel="noopener noreferrer" leftSection={<IconBrandWhatsapp size={18} />} variant="light" color="olive" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 46 } }}>Discuss your project on WhatsApp</Button>
          : <Button component={Link} href="/contacts#project-enquiry" variant="light" color="olive" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 46 } }}>Discuss your project</Button>}
      </aside>
    </section>
    <section className={styles.form} aria-labelledby="enquiry-contact">
      <h2 id="enquiry-contact">Tell Us About Your Project</h2>
      <p className={styles.formIntro}>Share your contact details and project requirements. Our team will use your selection to prepare a tailored quotation.</p>
      <div className={styles.formFields}>
        <TextInput label="Name" name="name" autoComplete="name" disabled />
        <TextInput label="Phone" name="phone" type="tel" autoComplete="tel" description="Include your country code for international enquiries." disabled />
        <TextInput label="Email" name="email" type="email" autoComplete="email" disabled />
        <TextInput label="Company / Project name" name="company" autoComplete="organization" disabled />
        <Textarea label="Comments" name="comments" minRows={4} disabled />
        <p className={styles.detail}>Online Product and Cart Enquiry submission is not available yet. Your selected products remain saved in this browser.</p>
        <Button type="button" disabled fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 50 } }}>Request My Quotation</Button>
      </div>
    </section>
  </div>;
}
