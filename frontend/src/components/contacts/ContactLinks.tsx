import type { ContactDetails } from '@/types/contacts';
import styles from './Contacts.module.css';

export function ContactLinks({ details }: { details: ContactDetails }) {
  return <div className={styles.contactLinks}>
    {details.phones?.map((phone) => <a key={phone.href} href={phone.href} aria-label={`Call ${phone.label}`}>{phone.label}</a>)}
    {details.emails?.map((email) => <a key={email} href={`mailto:${email}`} aria-label={`Email ${email}`}>{email}</a>)}
    {details.instagram && <a href={details.instagram.url} target="_blank" rel="noopener noreferrer" aria-label={`Instagram ${details.instagram.handle} (opens in a new tab)`}>Instagram: {details.instagram.handle}</a>}
  </div>;
}
