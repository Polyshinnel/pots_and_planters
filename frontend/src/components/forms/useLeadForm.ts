'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import type { FormErrors, LeadPayload, LeadSubmitAdapter, LeadValues } from '@/lib/forms/contracts';

const initialValues: LeadValues = { name: '', phone: '', email: '', company: '', message: '', role: '', roleOther: '' };
export function useLeadForm<P extends LeadPayload>({ validate, buildPayload, adapter }: {
  validate: (values: LeadValues) => FormErrors;
  buildPayload: (values: LeadValues) => P;
  adapter?: LeadSubmitAdapter<P>;
}) {
  const [values, setValues] = useState<LeadValues>(initialValues);
  const [errors, setErrors] = useState<FormErrors>({});
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState('');
  const [failed, setFailed] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);
  const locked = useRef(false);
  const mounted = useRef(true);
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
  function clearField(field: keyof FormErrors) {
    setErrors((previous) => ({ ...previous, [field]: undefined }));
    setNotice('');
  }
  function change(field: keyof LeadValues, value: string) {
    setValues((previous) => ({ ...previous, [field]: value }));
    clearField(field);
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (locked.current) return;
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
        setFailed(false);
        setNotice('Details checked. Online submission is not available yet. Nothing has been sent. Your details remain here for retry.');
        return;
      }
      locked.current = true;
      setPending(true);
      const result = await adapter(payload);
      if (!mounted.current) return;
      if (result.status === 'persisted' && !result.leadId.trim()) throw new Error('A persisted result requires a lead ID.');
      setFailed(result.status === 'failed');
      setNotice(result.status === 'persisted' ? 'Your enquiry has been saved. Catalogue delivery is not available yet.' : result.status === 'failed' ? 'Submission could not be confirmed. Your details remain here. Please try again.' : 'Online submission is unavailable. Nothing has been sent. Your details remain here for retry.');
    } catch {
      if (mounted.current) {
        setFailed(true);
        setNotice('Submission could not be confirmed. Check the selected item and your details, then try again. Your details have been retained.');
      }
    } finally {
      locked.current = false;
      if (mounted.current) setPending(false);
    }
  }
  return { values, errors, pending, notice, failed, formRef, change, clearField, submit };
}
export type LeadFormState = ReturnType<typeof useLeadForm>;
