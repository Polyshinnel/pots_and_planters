'use client';

import { Button, Textarea, TextInput } from '@mantine/core';
import { fieldLimits } from '@/lib/forms/validation';
import type { LeadFormState } from './useLeadForm';
import styles from './Forms.module.css';

export function ContactFields({ form, companyLabel = 'Company / Project name', compact = false, showCompany = true }: { form: LeadFormState; companyLabel?: string; compact?: boolean; showCompany?: boolean }) {
  const { values, errors, change, pending } = form;
  return <div className={`${styles.fields}${compact ? ` ${styles.compactFields}` : ''}`}>
    <TextInput label="Full name" name="name" required autoComplete="name" maxLength={fieldLimits.name} size={compact ? 'sm' : 'md'} disabled={pending} value={values.name} onChange={(event) => change('name', event.currentTarget.value)} error={errors.name} />
    <TextInput label="Phone" name="phone" type="tel" required autoComplete="tel" maxLength={fieldLimits.phone} description="Include your country code for international enquiries." size={compact ? 'sm' : 'md'} disabled={pending} value={values.phone} onChange={(event) => change('phone', event.currentTarget.value)} error={errors.phone} />
    <TextInput label="Email" name="email" type="email" required autoComplete="email" maxLength={fieldLimits.email} size={compact ? 'sm' : 'md'} disabled={pending} value={values.email} onChange={(event) => change('email', event.currentTarget.value)} error={errors.email} />
    {showCompany && <TextInput label={companyLabel} name="company" autoComplete="organization" maxLength={fieldLimits.company} size={compact ? 'sm' : 'md'} disabled={pending} value={values.company} onChange={(event) => change('company', event.currentTarget.value)} error={errors.company} />}
  </div>;
}
export function MessageField({ form, required = false, compact = false, className }: { form: LeadFormState; required?: boolean; compact?: boolean; className?: string }) {
  return <Textarea className={className} label="Message" name="message" required={required} size={compact ? 'sm' : 'md'} radius="xs" minRows={compact ? 2 : 4} autosize maxRows={compact ? 2 : 8} maxLength={fieldLimits.message} disabled={form.pending} value={form.values.message} onChange={(event) => form.change('message', event.currentTarget.value)} error={form.errors.message} />;
}
export function SubmissionFields({ form, label, catalogue = false, compact = false, privacyNotice = false }: { form: LeadFormState; label: string; catalogue?: boolean; compact?: boolean; privacyNotice?: boolean }) {
  return <>
    {!compact && <p className={styles.note}>Online submission is not available yet. Your details stay on this page and are not sent.{catalogue ? ' PDF download will be available after online submission is connected.' : ''}</p>}
    <Button type="submit" fullWidth loading={form.pending} disabled={form.pending} className={`${styles.submit}${compact ? ` ${styles.compactSubmit}` : ''}`}>{label}</Button>
    {privacyNotice && <p className={styles.privacyNotice}>By submitting this form, you agree to the <a href="/private-policy" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</p>}
    {form.notice && <p className={styles.notice} role={form.failed ? 'alert' : 'status'} aria-atomic="true">{form.notice}</p>}
  </>;
}
