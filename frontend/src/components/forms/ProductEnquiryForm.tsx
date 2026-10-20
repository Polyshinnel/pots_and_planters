'use client';

import Image from 'next/image';
import type { LeadFormProps, ProductEnquiryContext, ProductEnquiryPayload } from '@/lib/forms/contracts';
import { buildProductPayload } from '@/lib/forms/payloads';
import { validateLead } from '@/lib/forms/validation';
import { ContactFields, MessageField, SubmissionFields } from './FormFields';
import { LeadDialog } from './LeadDialog';
import { useLeadForm } from './useLeadForm';
import styles from './Forms.module.css';

type Props = LeadFormProps<ProductEnquiryPayload> & { product: ProductEnquiryContext; quantity: string };
/** Key by immutable model identity so changing products cannot retain the previous model's draft. */
export function ProductEnquiryForm(props: Props) {
  return <ProductForm key={JSON.stringify([props.product.id, props.product.sku])} {...props} />;
}
function ProductForm({ product, quantity, source, onSubmit }: Props) {
  const form = useLeadForm({ validate: (values) => validateLead(values, 'product_enquiry', quantity), buildPayload: (values) => buildProductPayload(values, product, quantity, source), adapter: onSubmit });
  return <form ref={form.formRef} onSubmit={form.submit} noValidate aria-label="Product enquiry" aria-busy={form.pending} className={`${styles.form} ${styles.contactForm} ${styles.compactForm}`}>
    <div className={styles.context}>
      {product.image && <Image src={product.image} alt="" width={72} height={72} />}
      <div><p><strong>{product.name}</strong></p><p>Model: {product.sku}</p>
        {!!product.specifications?.length && <dl>{product.specifications.map(({ label, value }) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>}
      </div>
    </div>
    <p className={`${styles.note} ${styles.compactNote}`}>Request a quote for this product. To enquire about several models, use Add to Enquiry and review your basket.</p>
    <ContactFields form={form} compact showCompany={false} />
    <MessageField form={form} compact />
    <SubmissionFields form={form} label="Request a Quote" compact privacyNotice />
  </form>;
}
export function ProductEnquiryDialog({ opened, onClose, ...props }: Props & { opened: boolean; onClose: () => void }) {
  return <LeadDialog opened={opened} onClose={onClose} title="Request a Quote" largeTitle narrow compactMobile><ProductEnquiryForm {...props} /></LeadDialog>;
}
