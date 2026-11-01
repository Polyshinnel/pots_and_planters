'use client';

import Image from 'next/image';
import { useRef, useState } from 'react';
import { Button, Select, TextInput } from '@mantine/core';
import { catalogueRoles, type CatalogueDownloadPayload, type CatalogueEnquiryContext, type DownloadGrant, type LeadFormProps } from '@/lib/forms/contracts';
import { buildCataloguePayload } from '@/lib/forms/payloads';
import { fieldLimits, validateLead } from '@/lib/forms/validation';
import { downloadCatalogue, enquirySubmissionsEnabled, submitEnquiry } from '@/lib/forms/enquiries.client';
import { ContactFields, SubmissionFields } from './FormFields';
import { LeadDialog } from './LeadDialog';
import { useLeadForm } from './useLeadForm';
import styles from './Forms.module.css';

type Props = LeadFormProps<CatalogueDownloadPayload> & { catalogue: CatalogueEnquiryContext };
export function CatalogueDownloadForm(props: Props) {
  return <CatalogueForm key={props.catalogue.id} {...props} />;
}
function CatalogueForm({ catalogue, source, onSubmit }: Props) {
  const [download, setDownload] = useState<{ grant: DownloadGrant; enquiryId: string; message: string; expired: boolean; unavailable: boolean } | null>(null);
  const completeRef = useRef<() => void>(() => {});
  async function startDownload(grant: DownloadGrant, enquiryId: string) {
    setDownload({ grant, enquiryId, message: 'Your request was saved. Preparing your catalogue…', expired: false, unavailable: false });
    try {
      await downloadCatalogue(grant, enquiryId);
      setDownload(null);
      completeRef.current();
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Your request was saved, but the catalogue could not be downloaded. Retry below.';
      setDownload({ grant, enquiryId, message, expired: message.includes('expired'), unavailable: message.includes('no longer available') });
    }
  }
  const form = useLeadForm({
    validate: (values) => validateLead(values, 'catalogue_download'),
    buildPayload: (values) => buildCataloguePayload(values, catalogue, source),
    adapter: onSubmit ?? submitEnquiry,
    preserveKeyOnPersisted: true,
    onPersisted: (result) => { if (result.downloadGrant) void startDownload(result.downloadGrant, result.leadId); },
  });
  completeRef.current = form.complete;
  return <form ref={form.formRef} onSubmit={form.submit} noValidate aria-label="Catalogue download" aria-busy={form.pending} className={`${styles.form} ${styles.contactForm} ${styles.compactForm}`}>
    <div className={styles.context}>
      {catalogue.coverImage && <Image src={catalogue.coverImage} alt="" width={128} height={80} unoptimized={catalogue.coverImage.includes('/storage/')} />}
      <div className={styles.contextCopy}><p className={styles.contextTitle}><strong>{catalogue.title}</strong></p><p className={styles.contextDescription}>Explore our collection of planters and greenery.</p>
        {!!catalogue.categoryIds?.length && <p>Categories: {catalogue.categoryIds.join(', ')}</p>}
      </div>
    </div>
    <ContactFields form={form} companyLabel="Company" compact />
    <Select label="Who are you?" name="role" required data={[...catalogueRoles]} value={form.values.role || null} onChange={(value) => form.change('role', value ?? '')} error={form.errors.role} disabled={form.pending} size="sm" radius="xs" placeholder="Select your role" withScrollArea maxDropdownHeight={240} comboboxProps={{ withinPortal: false }} />
    {form.values.role === 'other' && <TextInput label="Your role (optional)" name="roleOther" maxLength={fieldLimits.roleOther} value={form.values.roleOther} onChange={(event) => form.change('roleOther', event.currentTarget.value)} error={form.errors.roleOther} disabled={form.pending} size="sm" />}
    <SubmissionFields form={form} label="Get Catalogue" catalogue compact privacyNotice disabled={!enquirySubmissionsEnabled() || Boolean(download && !download.expired)} />
    {download && <div className={styles.downloadResult} role={download.expired ? 'alert' : 'status'} aria-live="polite">
      <p>{download.message}</p>
      {download.expired
        ? <Button type="button" variant="light" color="olive" loading={form.pending} disabled={form.pending} onClick={() => form.formRef.current?.requestSubmit()}>Request fresh download access</Button>
        : !download.unavailable && <Button type="button" variant="light" color="olive" onClick={() => void startDownload(download.grant, download.enquiryId)}>Retry catalogue download</Button>}
    </div>}
  </form>;
}
/** FE-07 owns the trigger and selected catalogue; changing ID resets the draft. */
export function CatalogueDownloadDialog({ opened, onClose, ...props }: Props & { opened: boolean; onClose: () => void }) {
  return <LeadDialog opened={opened} onClose={onClose} title="Get Catalogue" largeTitle narrow compactMobile><CatalogueDownloadForm {...props} /></LeadDialog>;
}
