import { IconArrowUpRight, IconBrandWhatsapp } from '@tabler/icons-react';
import { homeCopy } from '@/data/home.mock';
import { getProjectContact } from '@/lib/project-contact';
import styles from './Home.module.css';

export function ProjectContactLink({ final = false }: { final?: boolean }) {
  const contact = getProjectContact(homeCopy.contact.message);
  const Icon = contact.isWhatsApp ? IconBrandWhatsapp : IconArrowUpRight;
  return <a className={styles.button} href={contact.href}
    {...(contact.isWhatsApp ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
    <span>{final && contact.isWhatsApp ? 'Discuss on WhatsApp' : 'Discuss Your Project'}
      {contact.isWhatsApp && <span className={styles.srOnly}> (opens in a new tab)</span>}
    </span><Icon size={20} stroke={1.5} aria-hidden="true" />
  </a>;
}
