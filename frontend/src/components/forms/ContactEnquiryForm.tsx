'use client';

import type { ContactEnquiryPayload, LeadFormProps } from '@/lib/forms/contracts';
import { buildContactPayload } from '@/lib/forms/payloads';
import { validateLead } from '@/lib/forms/validation';
import { ContactFields, MessageField, SubmissionFields } from './FormFields';
import { useLeadForm } from './useLeadForm';
import styles from './Forms.module.css';

export function ContactEnquiryForm({ source, onSubmit }: LeadFormProps<ContactEnquiryPayload>) {
  const form = useLeadForm({ validate: (values) => validateLead(values, 'contact'), buildPayload: (values) => buildContactPayload(values, source), adapter: onSubmit });
  return <form ref={form.formRef} onSubmit={form.submit} noValidate aria-label="Contact enquiry" aria-busy={form.pending} className={`${styles.form} ${styles.contactForm} ${styles.compactForm}`}>
    <ContactFields form={form} compact />
    <MessageField form={form} required compact className={styles.contactMessage} />
    <SubmissionFields form={form} label="Send Enquiry" compact privacyNotice />
  </form>;
}
