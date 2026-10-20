'use client';

import { useState } from 'react';
import { ContactEnquiryForm } from '@/components/forms/ContactEnquiryForm';
import { LeadDialog } from '@/components/forms/LeadDialog';
import styles from './Contacts.module.css';

export function ContactEnquiryButton() {
  const [opened, setOpened] = useState(false);

  return <>
    <button type="button" className={styles.action} onClick={() => setOpened(true)}>Send an Enquiry</button>
    <LeadDialog opened={opened} onClose={() => setOpened(false)} title="Tell Us About Your Project" narrow compactMobile>
      <ContactEnquiryForm source={{ pagePath: '/contacts', placement: 'contacts_page_cta' }} />
    </LeadDialog>
  </>;
}
