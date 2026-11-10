'use client';

import { useRef, useState, type FormEvent } from 'react';
import { enquiryStoreV2, useEnquiryV2 } from '@/lib/enquiry/store-v2';
import { resolveProducts } from '@/lib/enquiry/product-resolve.client';
import { createAttemptManager, submitWithAttempt, serializeEnquiryV2, fingerprintEnquiryV2, type CartEnquiryV2Request } from '@/lib/enquiry/submit-v2';
import { validateContactFields, cleanText } from '@/lib/forms/validation';
import { Button, Textarea, TextInput } from '@mantine/core';
import Link from 'next/link';
import { IconBrandWhatsapp } from '@tabler/icons-react';
import { useWhatsAppUrl } from '@/components/layout/ContactsProvider';
import { enquiryWhatsAppUrl } from '@/lib/enquiry/whatsapp';
import { ResolvedCart } from './ResolvedCart';
import styles from './Enquiry.module.css';

export function EnquiryCartLayout() {
  const cart = useEnquiryV2();
  const locked = useRef(false);
  const manager = useRef(createAttemptManager());
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState('');
  const [reference, setReference] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [unavailable, setUnavailable] = useState<string[]>([]);
  const [confirm, setConfirm] = useState(false);
  const explicit = useRef(false);
  const enabled = process.env.NEXT_PUBLIC_CART_ENQUIRY_SUBMIT_ENABLED === 'true';
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (locked.current || !enabled) return;
    const data = new FormData(event.currentTarget);
    const values = Object.fromEntries(['name', 'phone', 'email', 'company', 'comments'].map((key) => [key, cleanText(String(data.get(key) ?? ''))]));
    const fieldErrors = validateContactFields({ name: values.name, phone: values.phone, email: values.email, company: values.company }, 64);
    if (values.comments.length > 5000) fieldErrors.message = 'Use at most 5000 characters.';
    setErrors(fieldErrors);
    if (Object.keys(fieldErrors).length) return;
    locked.current = true; setPending(true); setNotice(''); setUnavailable([]);
    try {
      const state = enquiryStoreV2.getState();
      if (!state.hydrated || !state.persistent || state.status !== 'ready') throw new Error('Saved cart data cannot be safely read. Please review your cart.');
      const items = state.items.map(({ productId, quantity }) => ({ productId, quantity }));
      const snapshot = JSON.stringify(items);
      const payload: CartEnquiryV2Request = { type: 'cart_enquiry', apiVersion: 2, items, name: values.name, phone: values.phone, email: values.email,
        company: values.company, message: values.comments, source: { pagePath: '/cart', placement: 'cart' } };
      serializeEnquiryV2(payload);
      const previous = manager.current.getAttempt('cart_enquiry', await fingerprintEnquiryV2(payload));
      if (!previous.ok && previous.code === 'already_persisted' && previous.attempt?.enquiryId) {
        setReference(previous.attempt.enquiryId); setNotice('This enquiry was already sent. Your selection remains saved.'); return;
      }
      let lines;
      try { lines = await resolveProducts(items); } catch { throw new Error('Product availability could not be checked. Your cart is saved. Please retry.'); }
      const missing = lines.filter((line) => line.status !== 'available').map((line) => line.item.productId);
      if (missing.length) { setUnavailable(missing); throw new Error('Some products are unavailable. Remove them manually or retry. ' + missing.join(', ')); }
      const result = await submitWithAttempt(payload, manager.current, explicit.current);
      explicit.current = false; setConfirm(false);
      if (result.ok) {
        setReference(result.result.enquiryId);
        setNotice('Your enquiry was sent. Your selection remains saved.' + (JSON.stringify(enquiryStoreV2.getState().items) !== snapshot ? ' Your cart changed during submission; the reference applies to the submitted selection.' : ''));
        return;
      }
      const failure = result.result;
      setUnavailable(failure?.unavailableProductIds ?? []);
      setErrors(Object.fromEntries(Object.entries(failure?.fieldErrors ?? {}).map(([key, messages]) => [key, messages[0]])));
      if (['payload_changed_requires_confirmation', 'attempt_expired_unknown_outcome', 'idempotency_conflict'].includes(result.code)) {
        setConfirm(true); throw new Error('The earlier enquiry may have been received. Confirm before starting a new request.');
      }
      throw new Error(result.code === 'attempt_storage_unavailable' ? 'Secure retry information could not be saved. Please enable browser storage and retry.'
        : failure?.code === 'products_unavailable' ? 'These products are unavailable: ' + failure.unavailableProductIds.join(', ') + '. Your entire cart is saved.'
        : failure?.code === 'feature_unavailable' ? 'Online cart enquiries are temporarily unavailable. Your selection remains saved.'
        : failure?.httpStatus === 429 ? 'Too many requests. Please wait and retry with the same details.'
        : Object.keys(failure?.fieldErrors ?? {}).length ? 'Please correct the highlighted fields.'
        : 'We could not confirm the result. Your enquiry may have been received. Retry with the same details to check safely.');
    } catch (error) { setNotice(error instanceof Error ? error.message : 'Please review your cart and retry.'); }
    finally { locked.current = false; setPending(false); }
  }
  const whatsappBaseUrl = useWhatsAppUrl();
  const whatsappUrl = enquiryWhatsAppUrl(whatsappBaseUrl, "Hello! I'd like to discuss planters for my project.");
  return <div className={styles.checkout}>
    <section className={styles.review} aria-labelledby="enquiry-products">
      <h2 id="enquiry-products">Your selection</h2>
      <ResolvedCart pending={pending} unavailable={unavailable} />
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
      <form onSubmit={submit} noValidate aria-busy={pending} className={styles.formFields}>
        <fieldset disabled={pending} style={{ border: 0, padding: 0, margin: 0 }}>
        <TextInput label="Name" name="name" error={errors.name} autoComplete="name" />
        <TextInput label="Phone" name="phone" error={errors.phone} type="tel" autoComplete="tel" description="Include your country code for international enquiries." />
        <TextInput label="Email" name="email" error={errors.email} type="email" autoComplete="email" />
        <TextInput label="Company / Project name" name="company" error={errors.company} autoComplete="organization" />
        <Textarea label="Comments" name="comments" error={errors.message} minRows={4} />
        <p className={styles.detail}>By submitting, you agree to our <Link href="/private-policy">Privacy Policy</Link>.</p>
        {notice && <p role="status">{notice}</p>}
        {reference && <p role="status">Enquiry reference: {reference}</p>}
        {!enabled && <p>Online cart enquiries are temporarily unavailable.</p>}
        <Button type="submit" disabled={!enabled || pending || !cart.items.length || cart.status !== 'ready'} fullWidth>Request My Quotation</Button>
        {confirm && <Button type="button" onClick={(event) => { explicit.current = true; event.currentTarget.closest('form')?.requestSubmit(); }}>Start a new request</Button>}
        </fieldset>
      </form>
    </section>
  </div>;
}
