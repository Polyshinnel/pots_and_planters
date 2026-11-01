'use client';

import { useState, type FormEvent } from 'react';
import { Button, Textarea, TextInput } from '@mantine/core';
import Link from 'next/link';
import { IconBrandWhatsapp } from '@tabler/icons-react';
import { useWhatsAppUrl } from '@/components/layout/ContactsProvider';
import { useEnquiry } from '@/lib/enquiry/store';
import { enquiryWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import type { EnquiryContactForm } from '@/lib/enquiry/submission';
import { EnquiryEmpty, EnquiryItems, EnquiryStorageNotice, EnquiryTotals } from './EnquiryItems';
import styles from './Enquiry.module.css';

export function EnquiryCheckout() {
  const whatsappBaseUrl = useWhatsAppUrl();
  const { items, totalQuantity, hydrated } = useEnquiry();
  const whatsappUrl = enquiryWhatsAppUrl(whatsappBaseUrl, "Hello! I'd like to discuss planters for my project.");
  const [form, setForm] = useState<EnquiryContactForm>({ name: '', phone: '', email: '', company: '', comments: '' });
  function change(field: keyof EnquiryContactForm, value: string) {
    setForm((previous) => ({ ...previous, [field]: value }));
  }
  function preventUnavailableSubmission(event: FormEvent) { event.preventDefault(); }
  if (!hydrated) return <p role="status">Loading your selection…</p>;
  if (!items.length) return <><EnquiryStorageNotice /><EnquiryEmpty /></>;
  return <div className={styles.checkout}>
    <section className={styles.review} aria-labelledby="enquiry-products"><h2 id="enquiry-products">Your selection</h2>
      <p role="status">{totalQuantity} units in your enquiry</p><EnquiryStorageNotice /><EnquiryItems /><EnquiryTotals />
      <aside className={styles.consultation} aria-labelledby="project-help">
        <h3 id="project-help">Need help with your project?</h3>
        <p>Not sure about sizes, finishes or quantities? Our team can help you select planters for your space, discuss alternatives and prepare a quotation for your requirements.</p>
        {whatsappUrl
          ? <Button component="a" href={whatsappUrl} target="_blank" rel="noreferrer" leftSection={<IconBrandWhatsapp size={18} />} variant="light" color="olive" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 46 } }}>Discuss your project on WhatsApp</Button>
          : <Button component={Link} href="/contacts#project-enquiry" variant="light" color="olive" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 46 } }}>Discuss your project</Button>}
      </aside>
    </section>
    <form className={styles.form} onSubmit={preventUnavailableSubmission} noValidate aria-labelledby="enquiry-contact">
      <h2 id="enquiry-contact">Tell Us About Your Project</h2>
      <p className={styles.formIntro}>Share your contact details and any project requirements. We'll review your selection and get back to you with a tailored quotation.</p>
      <div className={styles.formFields}>
        <TextInput label="Name" name="name" required autoComplete="name" maxLength={200} value={form.name} onChange={(event) => change('name', event.target.value)} />
        <TextInput label="Phone" name="phone" type="tel" required autoComplete="tel" maxLength={60} description="Include your country code for international enquiries." value={form.phone} onChange={(event) => change('phone', event.target.value)} />
        <TextInput label="Email" name="email" type="email" required autoComplete="email" maxLength={254} value={form.email} onChange={(event) => change('email', event.target.value)} />
        <TextInput label="Company / Project name" name="company" autoComplete="organization" maxLength={200} value={form.company} onChange={(event) => change('company', event.target.value)} />
        <Textarea label="Comments" name="comments" minRows={4} maxLength={5000} value={form.comments} onChange={(event) => change('comments', event.target.value)} />
        <p className={styles.detail}>Product and basket enquiries will be available after BE-08 introduces verified product records. Your selection remains saved in this browser when storage is available.</p>
        <Button type="button" disabled fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 50 } }}>Request My Quotation</Button>
      </div>
    </form>
  </div>;
}
