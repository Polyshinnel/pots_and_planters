'use client';

import type { Catalogue } from '@/types/catalog';
import { HomeImage } from '@/components/home/HomeImage';
import { homeImageSizes } from '@/components/home/image-sizes';
import homeStyles from '@/components/home/Home.module.css';
import styles from './Catalogues.module.css';

export function CatalogueCard({ catalogue, onOpen, variant = 'listing' }: {
  catalogue: Catalogue; onOpen: (catalogue: Catalogue) => void; variant?: 'home' | 'listing';
}) {
  const home = variant === 'home';
  return <button type="button" className={home ? homeStyles.catalogueCard : styles.card}
    onClick={() => onOpen(catalogue)} aria-haspopup="dialog" aria-label={`Download ${catalogue.title} catalogue`}>
    <div className={home ? homeStyles.catalogueImage : styles.cover}>
      <HomeImage image={{ src: catalogue.coverImage, alt: catalogue.coverAlt ?? `${catalogue.title} catalogue cover` }} sizes={homeImageSizes.catalogue} />
    </div>
    {home ? <h3>{catalogue.title}</h3> : <div className={styles.cardContent}>
      <h2>{catalogue.title}</h2>
      {catalogue.description && <p>{catalogue.description}</p>}
      <span className={styles.download}>Download Catalogue <span aria-hidden="true">↗</span></span>
    </div>}
  </button>;
}
