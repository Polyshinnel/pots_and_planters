'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import type { FormErrors, LeadPayload, LeadSubmissionResult, LeadSubmitAdapter, LeadValues } from '@/lib/forms/contracts';
import { newEnquiryIdempotencyKey } from '@/lib/forms/enquiries.client';

const initialValues: LeadValues = { name: '', phone: '', email: '', company: '', message: '', role: '', roleOther: '' };
export function useLeadForm<P extends LeadPayload>({ validate, buildPayload, adapter, onPersisted, preserveKeyOnPersisted = false }: {
  validate: (values: LeadValues) => FormErrors;
  buildPayload: (values: LeadValues) => P;
  adapter?: LeadSubmitAdapter<P>;
  onPersisted?: (result: Extract<LeadSubmissionResult, { status: 'persisted' }>) => void;
  preserveKeyOnPersisted?: boolean;
}) {
  const [values, setValues] = useState<LeadValues>(initialValues);
  const [errors, setErrors] = useState<FormErrors>({});
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState('');
  const [failed, setFailed] = useState(false);
  const [completed, setCompleted] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);
  const locked = useRef(false);
  const mounted = useRef(true);
  const attempt = useRef<{ fingerprint: string; key: string } | null>(null);
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
  function clearField(field: keyof FormErrors) {
    setErrors((previous) => ({ ...previous, [field]: undefined }));
    setNotice('');
  }
  function change(field: keyof LeadValues, value: string) {
    setValues((previous) => ({ ...previous, [field]: value }));
    setCompleted(false);
    clearField(field);
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (locked.current || completed) return;
    const nextErrors = validate(values);
    setErrors(nextErrors);
    setNotice('');
    if (Object.keys(nextErrors).length) {
      requestAnimationFrame(() => formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
      return;
    }
    try {
      const payload = buildPayload(values);
      if (!adapter) {
        setFailed(true);
        setNotice('Online submissions are unavailable. Nothing has been sent. Your details remain here.');
        return;
      }
      locked.current = true;
      setPending(true);
      const fingerprint = JSON.stringify(payload);
      if (attempt.current?.fingerprint !== fingerprint) attempt.current = { fingerprint, key: newEnquiryIdempotencyKey() };
      const result = await adapter(payload, attempt.current.key);
      if (!mounted.current) return;
      if (result.status === 'persisted' && !result.leadId.trim()) throw new Error('A persisted result requires a lead ID.');
      setFailed(result.status === 'failed');
      setNotice(result.status === 'persisted' ? 'Your enquiry has been sent.' : result.status === 'failed' ? 'Submission could not be confirmed. Your details remain here. Please try again.' : 'Online submissions are temporarily unavailable. Nothing has been sent. Your details remain here.');
      if (result.status === 'persisted') {
        if (!preserveKeyOnPersisted) {
          attempt.current = null;
          setCompleted(true);
        }
        onPersisted?.(result);
      }
    } catch (error) {
      if (mounted.current) {
        setFailed(true);
        const candidate = error && typeof error === 'object' ? error as { status?: unknown; message?: unknown; fieldErrors?: unknown } : {};
        if (candidate.status === 409) attempt.current = null;
        if (candidate.fieldErrors && typeof candidate.fieldErrors === 'object') setErrors((previous) => ({ ...previous, ...(candidate.fieldErrors as FormErrors) }));
        setNotice(typeof candidate.message === 'string' ? candidate.message : 'Submission could not be confirmed. Your details have been retained. Retry with the same details.');
      }
    } finally {
      locked.current = false;
      if (mounted.current) setPending(false);
    }
  }
  return { values, errors, pending, notice, failed, completed, formRef, change, clearField, submit, complete: () => { attempt.current = null; setCompleted(true); } };
}
export type LeadFormState = ReturnType<typeof useLeadForm>;
