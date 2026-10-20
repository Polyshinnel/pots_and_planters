'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button, Textarea, TextInput } from '@mantine/core';
import { IconBrandWhatsapp } from '@tabler/icons-react';
import { siteConfig } from '@/config/site';
import { useEnquiry } from '@/lib/enquiry/store';
import { enquiryWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import { buildEnquiryPayload, validateEnquiryForm, type EnquiryContactForm, type EnquiryFormErrors, type EnquirySubmissionPayload } from '@/lib/enquiry/submission';
import { EnquiryEmpty, EnquiryItems, EnquiryStorageNotice, EnquiryTotals } from './EnquiryItems';
import styles from './Enquiry.module.css';

const development = process.env.NODE_ENV === 'development';
export function EnquiryCheckout() {
  const { items, totalQuantity, hydrated } = useEnquiry();
  const whatsappUrl = enquiryWhatsAppUrl(siteConfig.whatsappUrl, "Hello! I'd like to discuss planters for my project.");
  const [form, setForm] = useState<EnquiryContactForm>({ name: '', phone: '', email: '', company: '', comments: '' });
  const [errors, setErrors] = useState<EnquiryFormErrors>({});
  const [payload, setPayload] = useState<EnquirySubmissionPayload | null>(null);
  const formElement = useRef<HTMLFormElement>(null);
  useEffect(() => { setPayload(null); }, [items, form]);
  function change(field: keyof EnquiryContactForm, value: string) {
    setForm((previous) => ({ ...previous, [field]: value }));
    setErrors((previous) => ({ ...previous, [field]: undefined }));
  }
  function submit(event: FormEvent) {
    event.preventDefault();
    const nextErrors = validateEnquiryForm(form);
    if (!items.length) nextErrors.cart = 'Add a product to your enquiry.';
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length) {
      setPayload(null);
      requestAnimationFrame(() => formElement.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
      return;
    }
    try { setPayload(buildEnquiryPayload(items, form)); }
    catch (error) { setErrors({ cart: error instanceof Error ? error.message : 'Review your enquiry.' }); }
  }
  if (!hydrated) return <p role="status">Loading your selection…</p>;
  if (!items.length) return <><EnquiryStorageNotice /><EnquiryEmpty /></>;
  return <div className={styles.checkout}>
    <section className={styles.review} aria-labelledby="enquiry-products"><h2 id="enquiry-products">Your selection</h2>
      <p role="status">{totalQuantity} units in your enquiry</p><EnquiryStorageNotice /><EnquiryItems /><EnquiryTotals />
      <aside className={styles.consultation} aria-labelledby="project-help">
        <h3 id="project-help">Need help with your project?</h3>
        <p>Not sure about sizes, finishes or quantities? Our team can help you select planters for your space, discuss alternatives and prepare a quotation for your requirements.</p>
        {whatsappUrl && <Button component="a" href={whatsappUrl} target="_blank" rel="noreferrer" leftSection={<IconBrandWhatsapp size={18} />} variant="light" color="olive" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 46 } }}>Discuss your project on WhatsApp</Button>}
      </aside>
    </section>
    <form ref={formElement} className={styles.form} onSubmit={submit} noValidate aria-labelledby="enquiry-contact">
      <h2 id="enquiry-contact">Tell Us About Your Project</h2>
      <p className={styles.formIntro}>Share your contact details and any project requirements. We'll review your selection and get back to you with a tailored quotation.</p>
      <div className={styles.formFields}>
        <TextInput label="Name" name="name" required autoComplete="name" maxLength={200} value={form.name} onChange={(event) => change('name', event.target.value)} error={errors.name} />
        <TextInput label="Phone" name="phone" type="tel" required autoComplete="tel" maxLength={60} description="Include your country code for international enquiries." value={form.phone} onChange={(event) => change('phone', event.target.value)} error={errors.phone} />
        <TextInput label="Email" name="email" type="email" required autoComplete="email" maxLength={254} value={form.email} onChange={(event) => change('email', event.target.value)} error={errors.email} />
        <TextInput label="Company / Project name" name="company" autoComplete="organization" maxLength={200} value={form.company} onChange={(event) => change('company', event.target.value)} error={errors.company} />
        <Textarea label="Comments" name="comments" minRows={4} maxLength={5000} value={form.comments} onChange={(event) => change('comments', event.target.value)} error={errors.comments} />
        <p className={styles.detail}>Online submission is not available yet. Contact details stay in this page and are not sent. Your product selection is saved in this browser when storage is available.</p>
        {errors.cart && <p role="alert">{errors.cart}</p>}
        <Button type="submit" variant="outline" fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 50 } }}>{development ? 'Preview payload (development only)' : 'Check enquiry details'}</Button>
        <Button type="button" disabled fullWidth styles={{ label: { whiteSpace: 'normal' }, root: { height: 'auto', minHeight: 50 } }}>Request My Quotation</Button>
        {payload && <div role="status"><p>Details checked. Nothing has been sent. Your enquiry cannot be submitted until online submission is available.</p>
          {development && <details open><summary>Development-only payload preview — not submitted</summary><pre className={styles.preview}>{JSON.stringify(payload, null, 2)}</pre></details>}
        </div>}
      </div>
    </form>
  </div>;
}
