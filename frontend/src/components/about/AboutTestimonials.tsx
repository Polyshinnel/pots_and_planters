'use client';

import { useState } from 'react';
import { Modal } from '@mantine/core';
import { IconArrowLeft, IconArrowRight } from '@tabler/icons-react';
import { Autoplay } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/react';
import { aboutTestimonials } from '@/data/about.mock';
import homeStyles from '@/components/home/Home.module.css';
import styles from './About.module.css';
import 'swiper/css';

export function AboutTestimonials() {
  const [swiper, setSwiper] = useState<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [selectedReview, setSelectedReview] = useState<(typeof aboutTestimonials)[number] | null>(null);
  const paginationDotCount = 4;
  const activeDotIndex = aboutTestimonials.length > 1
    ? Math.round(activeIndex * (paginationDotCount - 1) / (aboutTestimonials.length - 1))
    : 0;

  return <>
    <Swiper className={styles.testimonialSwiper} modules={[Autoplay]} aria-label="Customer reviews" aria-roledescription="carousel"
      loop slidesPerView={1} slidesPerGroup={1} spaceBetween={16} onSwiper={setSwiper}
      onSlideChange={(instance) => setActiveIndex(instance.realIndex)}
      autoplay={{ delay: 10000, disableOnInteraction: false }}
      breakpoints={{ 576: { slidesPerView: 2, spaceBetween: 16 }, 992: { slidesPerView: 3, spaceBetween: 24 } }}>
      {aboutTestimonials.map((review) => <SwiperSlide key={review.name} className={styles.testimonialSlide}>
        <figure className={styles.testimonialCard}>
          <span className={styles.quoteMark} aria-hidden="true">“</span>
          <blockquote><p>{review.expandable ? `${review.quote.slice(0, 190).trimEnd()} (...)` : review.quote}</p></blockquote>
          {review.expandable && <button className={styles.showMore} type="button" onClick={() => setSelectedReview(review)}>Show More</button>}
          <figcaption>{review.name}</figcaption>
        </figure>
      </SwiperSlide>)}
    </Swiper>
    <div className={`${homeStyles.productControls} ${styles.testimonialControls}`}>
      <div className={homeStyles.cataloguePagination}>
        <div className={`${homeStyles.catalogueDots} ${homeStyles.desktopProductDots}`} role="group" aria-label="Choose a review">
          {aboutTestimonials.map((review, index) => <button key={review.name} type="button"
            className={`${homeStyles.catalogueDot} ${activeIndex === index ? homeStyles.catalogueDotActive : ''}`}
            aria-label={`Go to review by ${review.name}`} aria-current={activeIndex === index ? 'true' : undefined}
            onClick={() => swiper?.slideToLoop(index)} />)}
        </div>
        <div className={`${homeStyles.catalogueDots} ${homeStyles.mobileProductDots}`} role="group" aria-label="Choose a review">
          {Array.from({ length: paginationDotCount }, (_, index) => {
            const reviewIndex = Math.round(index * (aboutTestimonials.length - 1) / (paginationDotCount - 1));
            return <button key={index} type="button"
              className={`${homeStyles.catalogueDot} ${activeDotIndex === index ? homeStyles.catalogueDotActive : ''}`}
              aria-label={`Go to review by ${aboutTestimonials[reviewIndex].name}`} aria-current={activeDotIndex === index ? 'true' : undefined}
              onClick={() => swiper?.slideToLoop(reviewIndex)} />;
          })}
        </div>
        <span className={homeStyles.cataloguePosition} aria-live="polite">{String(activeIndex + 1).padStart(2, '0')} <span aria-hidden="true">/</span> {String(aboutTestimonials.length).padStart(2, '0')}</span>
      </div>
      <div className={homeStyles.catalogueArrows}>
        <button type="button" className={homeStyles.productControl} aria-label="Previous review" onClick={() => swiper?.slidePrev()}><IconArrowLeft size={20} stroke={1.5} aria-hidden="true" /></button>
        <button type="button" className={homeStyles.productControl} aria-label="Next review" onClick={() => swiper?.slideNext()}><IconArrowRight size={20} stroke={1.5} aria-hidden="true" /></button>
      </div>
    </div>
    <Modal opened={!!selectedReview} onClose={() => setSelectedReview(null)} title={selectedReview?.name} centered size="lg">
      {selectedReview && <div className={styles.fullReview}><span className={styles.quoteMark} aria-hidden="true">“</span><p>{selectedReview.quote}</p></div>}
    </Modal>
  </>;
}
