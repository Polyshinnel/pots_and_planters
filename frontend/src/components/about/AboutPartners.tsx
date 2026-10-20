'use client';

import Image from 'next/image';
import { useState } from 'react';
import { IconArrowLeft, IconArrowRight } from '@tabler/icons-react';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/react';
import { aboutPartners } from '@/data/about.mock';
import homeStyles from '@/components/home/Home.module.css';
import styles from './About.module.css';
import 'swiper/css';

export function AboutPartners() {
  const [swiper, setSwiper] = useState<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const paginationDotCount = 4;
  const activeDotIndex = aboutPartners.length > 1
    ? Math.round(activeIndex * (paginationDotCount - 1) / (aboutPartners.length - 1))
    : 0;

  return <>
    <Swiper className={styles.partnersSwiper} aria-label="Our partners" aria-roledescription="carousel"
      loop slidesPerView={1} slidesPerGroup={1} spaceBetween={16} onSwiper={setSwiper}
      onSlideChange={(instance) => setActiveIndex(instance.realIndex)}
      breakpoints={{ 480: { slidesPerView: 2 }, 680: { slidesPerView: 3, spaceBetween: 20 }, 900: { slidesPerView: 4, spaceBetween: 20 }, 1200: { slidesPerView: 5, spaceBetween: 24 } }}>
      {aboutPartners.map((partner) => <SwiperSlide key={partner.name} className={styles.partnerSlide}>
        <a className={styles.partnerCard} href={partner.href} target="_blank" rel="noreferrer" aria-label={partner.name}>
          <Image src={partner.image} alt={partner.name} width={200} height={150} sizes="(max-width: 479px) 80vw, (max-width: 679px) 40vw, (max-width: 899px) 28vw, (max-width: 1199px) 22vw, 18vw" />
        </a>
      </SwiperSlide>)}
    </Swiper>
    <div className={`${homeStyles.productControls} ${styles.partnerControls}`}>
      <div className={homeStyles.cataloguePagination}>
        <div className={`${homeStyles.catalogueDots} ${homeStyles.desktopProductDots}`} role="group" aria-label="Choose a partner">
          {aboutPartners.map((partner, index) => <button key={partner.name} type="button"
            className={`${homeStyles.catalogueDot} ${activeIndex === index ? homeStyles.catalogueDotActive : ''}`}
            aria-label={`Go to ${partner.name}`} aria-current={activeIndex === index ? 'true' : undefined}
            onClick={() => swiper?.slideToLoop(index)} />)}
        </div>
        <div className={`${homeStyles.catalogueDots} ${homeStyles.mobileProductDots}`} role="group" aria-label="Choose a partner">
          {Array.from({ length: paginationDotCount }, (_, index) => {
            const partnerIndex = Math.round(index * (aboutPartners.length - 1) / (paginationDotCount - 1));
            return <button key={index} type="button"
              className={`${homeStyles.catalogueDot} ${activeDotIndex === index ? homeStyles.catalogueDotActive : ''}`}
              aria-label={`Go to ${aboutPartners[partnerIndex].name}`} aria-current={activeDotIndex === index ? 'true' : undefined}
              onClick={() => swiper?.slideToLoop(partnerIndex)} />;
          })}
        </div>
        <span className={homeStyles.cataloguePosition} aria-live="polite">{String(activeIndex + 1).padStart(2, '0')} <span aria-hidden="true">/</span> {String(aboutPartners.length).padStart(2, '0')}</span>
      </div>
      <div className={homeStyles.catalogueArrows}>
        <button type="button" className={homeStyles.productControl} aria-label="Previous partner" onClick={() => swiper?.slidePrev()}><IconArrowLeft size={20} stroke={1.5} aria-hidden="true" /></button>
        <button type="button" className={homeStyles.productControl} aria-label="Next partner" onClick={() => swiper?.slideNext()}><IconArrowRight size={20} stroke={1.5} aria-hidden="true" /></button>
      </div>
    </div>
  </>;
}
