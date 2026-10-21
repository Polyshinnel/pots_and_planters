'use client';

import type { Catalogue } from '@/types/catalog';
import { HomeImage } from '@/components/home/HomeImage';
import { homeImageSizes } from '@/components/home/image-sizes';
import styles from './Catalogues.module.css';

export function CatalogueCard({ catalogue, onOpen, variant = 'listing' }: {
  catalogue: Catalogue; onOpen: (catalogue: Catalogue) => void; variant?: 'home' | 'listing';
}) {
  return <button type="button" className={`${styles.card} ${variant === 'home' ? styles.homeCard : ''}`}
    onClick={() => onOpen(catalogue)} aria-haspopup="dialog" aria-label={`Download ${catalogue.title} catalogue`}>
    <div className={styles.cover}>
      <HomeImage image={catalogue.coverImage ? { src: catalogue.coverImage, alt: catalogue.coverAlt ?? `${catalogue.title} catalogue cover` } : null} sizes={homeImageSizes.catalogue} />
    </div>
    <div className={styles.cardContent}>
      {variant === 'home' ? <h3>{catalogue.title}</h3> : <h2>{catalogue.title}</h2>}
      {variant === 'home' ? <p>{catalogue.description ?? ''}</p> : catalogue.description && <p>{catalogue.description}</p>}
      <span className={styles.download}>Download Catalogue <span aria-hidden="true">↗</span></span>
    </div>
  </button>;
}
