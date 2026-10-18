'use client';

import { useState } from 'react';
import { IconArrowLeft, IconArrowRight } from '@tabler/icons-react';
import { Autoplay } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/react';
import type { ProductPreview } from '@/types/home';
import { ProductCard } from './ProductCard';
import styles from './Home.module.css';
import 'swiper/css';

export function FeaturedProductsSlider({ products }: { products: ProductPreview[] }) {
  const [swiper, setSwiper] = useState<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const paginationDotCount = 4;
  const activeDotIndex = products.length > 1
    ? Math.round(activeIndex * (paginationDotCount - 1) / (products.length - 1))
    : 0;

  return <>
    <Swiper
      className={styles.productSwiper}
      modules={[Autoplay]}
      aria-label="Featured products"
      aria-roledescription="carousel"
      loop
      slidesPerView={1.12}
      centeredSlides
      slidesPerGroup={1}
      spaceBetween={16}
      onSwiper={setSwiper}
      onSlideChange={(instance) => setActiveIndex(instance.realIndex)}
      autoplay={{ delay: 8000, disableOnInteraction: false }}
      breakpoints={{
        576: { slidesPerView: 1.12, centeredSlides: true, spaceBetween: 16 },
        768: { slidesPerView: 2, centeredSlides: false, spaceBetween: 16 },
        992: { slidesPerView: 4, centeredSlides: false, spaceBetween: 24 },
      }}
    >
      {products.map((product) => <SwiperSlide key={product.id} className={styles.productSlide}>
        <ProductCard product={product} />
      </SwiperSlide>)}
    </Swiper>
    <div className={styles.productControls}>
      <div className={styles.cataloguePagination}>
        <div className={`${styles.catalogueDots} ${styles.desktopProductDots}`} role="group" aria-label="Choose a product">
          {products.map((product, index) => <button
            key={product.id}
            type="button"
            className={`${styles.catalogueDot} ${activeIndex === index ? styles.catalogueDotActive : ''}`}
            aria-label={`Go to ${product.name}`}
            aria-current={activeIndex === index ? 'true' : undefined}
            onClick={() => swiper?.slideToLoop(index)}
          />)}
        </div>
        <div className={`${styles.catalogueDots} ${styles.mobileProductDots}`} role="group" aria-label="Choose a product">
          {Array.from({ length: paginationDotCount }, (_, index) => <button
            key={index}
            type="button"
            className={`${styles.catalogueDot} ${activeDotIndex === index ? styles.catalogueDotActive : ''}`}
            aria-label={`Go to product ${Math.round(index * (products.length - 1) / (paginationDotCount - 1)) + 1}`}
            aria-current={activeDotIndex === index ? 'true' : undefined}
            onClick={() => swiper?.slideToLoop(Math.round(index * (products.length - 1) / (paginationDotCount - 1)))}
          />)}
        </div>
        <span className={styles.cataloguePosition} aria-live="polite">
          {String(activeIndex + 1).padStart(2, '0')} <span aria-hidden="true">/</span> {String(products.length).padStart(2, '0')}
        </span>
      </div>
      <div className={styles.catalogueArrows}>
        <button type="button" className={styles.productControl} aria-label="Previous product" onClick={() => swiper?.slidePrev()}>
          <IconArrowLeft size={20} stroke={1.5} aria-hidden="true" />
        </button>
        <button type="button" className={styles.productControl} aria-label="Next product" onClick={() => swiper?.slideNext()}>
          <IconArrowRight size={20} stroke={1.5} aria-hidden="true" />
        </button>
      </div>
    </div>
  </>;
}
