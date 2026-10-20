'use client';

import { useState } from 'react';
import { IconArrowUpRight } from '@tabler/icons-react';
import { ContactEnquiryForm } from '@/components/forms/ContactEnquiryForm';
import { LeadDialog } from '@/components/forms/LeadDialog';
import styles from './About.module.css';

export function AboutContactButton() {
  const [opened, setOpened] = useState(false);

  return <>
    <button type="button" className={`${styles.textLink} ${styles.contactButton}`} onClick={() => setOpened(true)}>
      Talk to Our Team <IconArrowUpRight size={20} aria-hidden="true" />
    </button>
    <LeadDialog opened={opened} onClose={() => setOpened(false)} title="Tell Us About Your Project" narrow compactMobile>
      <ContactEnquiryForm source={{ pagePath: '/about', placement: 'about-hero' }} />
    </LeadDialog>
  </>;
}
