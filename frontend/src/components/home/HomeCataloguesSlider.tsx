'use client';

import { useState } from 'react';
import { IconArrowLeft, IconArrowRight } from '@tabler/icons-react';
import Link from 'next/link';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/react';
import type { CataloguePreview } from '@/types/home';
import { HomeImage } from './HomeImage';
import { homeImageSizes } from './image-sizes';
import styles from './Home.module.css';

export function HomeCataloguesSlider({ catalogues }: { catalogues: CataloguePreview[] }) {
  const [swiper, setSwiper] = useState<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);

  return <>
    <Swiper
      className={styles.catalogueSwiper}
      aria-label="Catalogues"
      aria-roledescription="carousel"
      loop
      slidesPerView={1}
      slidesPerGroup={1}
      spaceBetween={16}
      onSwiper={setSwiper}
      onSlideChange={(instance) => setActiveIndex(instance.realIndex)}
      breakpoints={{
        576: { slidesPerView: 2, spaceBetween: 16 },
        992: { slidesPerView: 3, spaceBetween: 24 },
      }}
    >
      {catalogues.map((catalogue) => <SwiperSlide key={catalogue.id} className={styles.catalogueSlide}>
        <Link href="/catalogues" className={styles.catalogueCard}>
          <div className={styles.catalogueImage}><HomeImage image={catalogue.image} sizes={homeImageSizes.catalogue} /></div>
          <h3>{catalogue.title}</h3>
        </Link>
      </SwiperSlide>)}
    </Swiper>
    <div className={styles.catalogueControls}>
      <div className={styles.cataloguePagination}>
        <div className={styles.catalogueDots} role="group" aria-label="Choose a catalogue">
          {catalogues.map((catalogue, index) => <button
            key={catalogue.id}
            type="button"
            className={`${styles.catalogueDot} ${activeIndex === index ? styles.catalogueDotActive : ''}`}
            aria-label={`Go to ${catalogue.title}`}
            aria-current={activeIndex === index ? 'true' : undefined}
            onClick={() => swiper?.slideToLoop(index)}
          />)}
        </div>
        <span className={styles.cataloguePosition} aria-live="polite">
          {String(activeIndex + 1).padStart(2, '0')} <span aria-hidden="true">/</span> {String(catalogues.length).padStart(2, '0')}
        </span>
      </div>
      <div className={styles.catalogueArrows}>
        <button type="button" className={styles.catalogueControl} aria-label="Previous catalogue" onClick={() => swiper?.slidePrev()}>
          <IconArrowLeft size={20} stroke={1.5} aria-hidden="true" />
        </button>
        <button type="button" className={styles.catalogueControl} aria-label="Next catalogue" onClick={() => swiper?.slideNext()}>
          <IconArrowRight size={20} stroke={1.5} aria-hidden="true" />
        </button>
      </div>
    </div>
  </>;
}
