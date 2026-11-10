'use client';

import Image from 'next/image';
import type { LeadFormProps, ProductEnquiryContext, ProductEnquiryPayload } from '@/lib/forms/contracts';
import { buildProductPayload } from '@/lib/forms/payloads';
import { validateLead } from '@/lib/forms/validation';
import { ContactFields, MessageField, SubmissionFields } from './FormFields';
import { LeadDialog } from './LeadDialog';
import { useLeadForm } from './useLeadForm';
import styles from './Forms.module.css';
import { submitWithAttempt, createAttemptManager, type ProductEnquiryV2Request } from '@/lib/enquiry/submit-v2';
import { useRef, useState } from 'react';
import { Button } from '@mantine/core';

type Props = LeadFormProps<ProductEnquiryPayload> & { product: ProductEnquiryContext; quantity: string };
/** Key by immutable model identity so changing products cannot retain the previous model's draft. */
export function ProductEnquiryForm(props: Props) {
  return <ProductForm key={JSON.stringify([props.product.id, props.product.sku])} {...props} />;
}
function ProductForm({ product, quantity, source }: Props) {
  const [reference, setReference] = useState('');
  const [recoveryRequired, setRecoveryRequired] = useState(false);
  const explicitNewAttempt = useRef(false);
  const attemptManager = useRef<ReturnType<typeof createAttemptManager> | null>(null);
  if (!attemptManager.current) attemptManager.current = createAttemptManager();
  const enabled = process.env.NEXT_PUBLIC_PRODUCT_ENQUIRY_SUBMIT_ENABLED === 'true';
  const form = useLeadForm({ validate: (values) => validateLead(values, 'product_enquiry', quantity, 64), buildPayload: (values) => buildProductPayload(values, product, quantity, source),
    adapter: async (payload) => {
      const request: ProductEnquiryV2Request = { type: 'product_enquiry', apiVersion: 2, product: { productId: product.id, quantity: payload.quantity },
        name: payload.name, phone: payload.phone, email: payload.email,
        ...(payload.company ? { company: payload.company } : {}), ...(payload.message ? { message: payload.message } : {}),
        source: { pagePath: source?.pagePath?.split(/[?#]/)[0] || window.location.pathname, placement: 'product_detail' } };
      const result = await submitWithAttempt(request, attemptManager.current!, explicitNewAttempt.current);
      explicitNewAttempt.current = false;
      setRecoveryRequired(false);
      if (result.ok) { setReference(result.result.enquiryId); return { status: 'persisted', leadId: result.result.enquiryId }; }
      const failure = result.result;
      const needsConfirmation = ['payload_changed_requires_confirmation', 'attempt_expired_unknown_outcome', 'idempotency_conflict'].includes(result.code);
      setRecoveryRequired(needsConfirmation);
      const fields = Object.fromEntries(Object.entries(failure?.fieldErrors ?? {}).map(([key, errors]) => [key === 'product.quantity' ? 'quantity' : key, errors[0]]));
      const message = result.code === 'already_persisted' ? 'This request was already sent. Reference: ' + (failure?.requestId ?? '')
        : result.code === 'attempt_storage_unavailable' ? 'Secure retry information could not be saved. Nothing was sent.'
          : result.code === 'payload_changed_requires_confirmation' || result.code === 'attempt_expired_unknown_outcome' || result.code === 'idempotency_conflict'
            ? 'The earlier request may have been received. Confirm below before creating a new request.'
              : failure?.code === 'products_unavailable' ? 'This product is currently unavailable for an enquiry. Please refresh the page or return to the catalogue.'
                : failure?.httpStatus === 429 ? 'Too many requests. Please wait and retry.'
                  : failure?.fieldErrors && Object.keys(failure.fieldErrors).length ? 'Please correct the highlighted fields.'
                    : 'We could not confirm the result. Your request may have been received; retry to check it safely.';
      throw Object.assign(new Error(message), { status: failure?.httpStatus, fieldErrors: fields });
    }, onPersisted: () => {} });
  return <form ref={form.formRef} onSubmit={form.submit} noValidate aria-label="Product enquiry" aria-busy={form.pending} className={`${styles.form} ${styles.contactForm} ${styles.compactForm}`}>
    <div className={styles.context}>
      {product.image && <Image src={product.image} alt="" width={72} height={72} unoptimized={product.image.includes('/storage/')} />}
      <div><p><strong>{product.name}</strong></p><p>Model: {product.sku}</p>
        {!!product.specifications?.length && <dl>{product.specifications.map(({ label, value }) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>}
      </div>
    </div>
    <p className={`${styles.note} ${styles.compactNote}`}>Request personal pricing for this product. This is an enquiry, not an order.</p>
    <ContactFields form={form} compact showCompany={false} phoneMaxLength={64} />
    {form.errors.quantity && <p role="alert" className={styles.error}>{form.errors.quantity}</p>}
    <MessageField form={form} compact />
    <SubmissionFields form={form} label="Request a Quote" compact privacyNotice disabled={!enabled} unavailableMessage="Online product enquiries are temporarily unavailable." />
    {recoveryRequired && <Button type="button" variant="subtle" disabled={form.pending} onClick={() => { explicitNewAttempt.current = true; setRecoveryRequired(false); form.formRef.current?.requestSubmit(); }}>Start a new request</Button>}
    {reference && <p role="status" className={styles.notice}>Your request for personal pricing was sent. Reference: {reference}</p>}
  </form>;
}
export function ProductEnquiryDialog({ opened, onClose, ...props }: Props & { opened: boolean; onClose: () => void }) {
  return <LeadDialog opened={opened} onClose={onClose} title="Request a Quote" largeTitle narrow compactMobile><ProductEnquiryForm {...props} /></LeadDialog>;
}
