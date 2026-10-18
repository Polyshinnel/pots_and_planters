'use client';

import { useEffect, useRef, useState } from 'react';
import { Fancybox } from '@fancyapps/ui/dist/fancybox/';
import { Swiper, SwiperSlide } from 'swiper/react';
import { A11y, Keyboard } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import { IconZoomIn } from '@tabler/icons-react';
import type { ProductImage } from '@/types/catalog';
import { HomeImage } from '@/components/home/HomeImage';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import 'swiper/css';
import styles from './Product.module.css';

export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const root = useRef<HTMLDivElement>(null);
  const swiper = useRef<SwiperInstance | null>(null);
  const [active, setActive] = useState(0);
  useEffect(() => {
    const container = root.current;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    Fancybox.bind(container, '[data-fancybox]', { placeFocusBack: true, idle: false, fadeEffect: !reduced, zoomEffect: !reduced });
    return () => { Fancybox.unbind(container); Fancybox.getInstance()?.close(); };
  }, [images]);

  const slide = (image: ProductImage, index: number) => <a href={image.src} data-fancybox="product-gallery" data-caption={image.alt}
    className={styles.galleryImage} aria-label={`Enlarge ${image.alt}`} tabIndex={index === active ? 0 : -1}>
    <HomeImage image={image} sizes="(min-width: 1440px) 700px, (min-width: 992px) 52vw, 100vw" priority={index === 0} />
    <span className={styles.zoom}><IconZoomIn size={18} aria-hidden="true" /> View larger</span>
  </a>;

  return <div ref={root} className={styles.gallery} role="region" aria-label={`${name} images`}>
    {!images.length ? <div className={styles.galleryImage}><HomeImage image={null} sizes="100vw" /></div>
      : images.length === 1 ? slide(images[0], 0)
        : <Swiper modules={[A11y, Keyboard]} keyboard={{ enabled: true, onlyInViewport: true }} slidesPerView={1}
          onSwiper={(instance) => { swiper.current = instance; }} onSlideChange={(instance) => setActive(instance.activeIndex)}>
          {images.map((image, index) => <SwiperSlide key={image.src}>{slide(image, index)}</SwiperSlide>)}
        </Swiper>}
    {images.length > 1 && <>
      <p className={styles.galleryHint} aria-live="polite">Image {active + 1} of {images.length} · Swipe or choose an image</p>
      <div className={styles.thumbnails} aria-label="Choose product image">
        {images.map((image, index) => <button key={image.src} type="button" aria-label={`Show image ${index + 1}: ${image.alt}`}
          aria-pressed={active === index} onClick={() => swiper.current?.slideTo(index)}>
          <HomeImage image={image} sizes="72px" />
        </button>)}
      </div>
    </>}
  </div>;
}
