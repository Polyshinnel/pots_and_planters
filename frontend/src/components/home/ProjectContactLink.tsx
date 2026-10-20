'use client';

import { useState } from 'react';
import { IconArrowUpRight, IconBrandWhatsapp } from '@tabler/icons-react';
import { homeCopy } from '@/data/home.mock';
import { getProjectContact } from '@/lib/project-contact';
import { ContactEnquiryForm } from '@/components/forms/ContactEnquiryForm';
import { LeadDialog } from '@/components/forms/LeadDialog';
import styles from './Home.module.css';

export function ProjectContactLink({ final = false, hero = false }: { final?: boolean; hero?: boolean }) {
  const contact = getProjectContact(homeCopy.contact.message);
  const [opened, setOpened] = useState(false);
  if (final && contact.isWhatsApp) {
    return <a className={styles.button} href={contact.href} target="_blank" rel="noopener noreferrer">
      <span>Discuss on WhatsApp <span className={styles.srOnly}> (opens in a new tab)</span></span>
      <IconBrandWhatsapp size={20} stroke={1.5} aria-hidden="true" />
    </a>;
  }
  return <>
    <a className={`${styles.button}${hero ? ` ${styles.heroButton}` : ''}`} href={hero ? '/#contact-cta' : contact.href}
      onClick={(event) => { event.preventDefault(); setOpened(true); }}>
      <span>Discuss Your Project</span><IconArrowUpRight size={20} stroke={1.5} aria-hidden="true" />
    </a>
    <LeadDialog opened={opened} onClose={() => setOpened(false)} title="Discuss Your Project" largeTitle narrow compactMobile>
      <ContactEnquiryForm source={{ pagePath: '/', placement: hero ? 'home-hero' : final ? 'home-final-cta' : 'home-project-cta' }} />
    </LeadDialog>
  </>;
}
