import { IconArrowUpRight, IconMapPin } from '@tabler/icons-react';
import type { Showroom } from '@/types/contacts';
import { showroomDirectionsUrl } from '@/lib/contacts';
import { ContactLinks } from './ContactLinks';
import styles from './Contacts.module.css';

export function Showrooms({ showrooms }: { showrooms: readonly Showroom[] }) {
  if (!showrooms.length) return null;
  return <section className={styles.section} aria-labelledby="showrooms-title">
    <h2 id="showrooms-title">Our Showrooms</h2>
    <p>Find contact details for each showroom and search for directions using its address.</p>
    <div className={styles.showrooms}>
      {showrooms.map((showroom) => {
        const directions = showroomDirectionsUrl(showroom);
        return <article key={showroom.id} className={styles.showroom} aria-labelledby={`${showroom.id}-title`}>
          <p className={styles.location}><IconMapPin size={18} stroke={1.5} aria-hidden="true" />{[showroom.city, showroom.country].filter(Boolean).join(', ')}</p>
          <h3 id={`${showroom.id}-title`}>{showroom.name}</h3>
          {showroom.description && <p>{showroom.description}</p>}
          <address>{showroom.address}</address>
          <ContactLinks details={showroom} />
          {!!showroom.openingHours?.length && <div><h4>Opening hours</h4>{showroom.openingHours.map((hours) => <p key={hours}>{hours}</p>)}</div>}
          {directions && <a className={styles.directions} href={directions} target="_blank" rel="noopener noreferrer" aria-label={`Get Directions to ${showroom.name} (address search, opens in a new tab)`}>Get Directions<IconArrowUpRight size={18} aria-hidden="true" /></a>}
        </article>;
      })}
    </div>
  </section>;
}
