'use client';

import { useEffect, useRef } from 'react';
import { Fancybox } from '@fancyapps/ui/dist/fancybox/';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import type { CataloguePreview } from '@/types/home';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import styles from './Home.module.css';

export function InspirationGallery({ items }: { items: CataloguePreview[] }) {
  const root = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const container = root.current;
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const bind = () => {
      Fancybox.unbind(container);
      Fancybox.bind(container, '[data-fancybox]', {
        placeFocusBack: true,
        idle: false,
        fadeEffect: !motion.matches,
        zoomEffect: !motion.matches,
        showClass: motion.matches ? false : 'f-zoomInUp',
        hideClass: motion.matches ? false : 'f-fadeOut',
        Carousel: { transition: motion.matches ? false : 'slide' },
      });
    };
    bind();
    motion.addEventListener('change', bind);
    return () => {
      motion.removeEventListener('change', bind);
      Fancybox.unbind(container);
      Fancybox.getInstance()?.close();
    };
  }, []);

  return <div ref={root} className={styles.inspirationGrid}>
    {items.map((item) => <a key={item.id} className={styles.galleryItem} href={item.image.src}
      data-fancybox="home-inspiration" data-caption={`${item.title} — catalogue cover`}
      aria-label={`Enlarge ${item.title} catalogue cover`}>
      <div className={styles.catalogueImage}><HomeImage image={item.image} sizes={homeImageSizes.inspiration} /></div>
      <span>{item.title} <span aria-hidden="true">↗</span></span>
    </a>)}
  </div>;
}
