'use client';

import { useRef, useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Button } from '@mantine/core';
import { useReducedMotion } from '@mantine/hooks';
import { Fancybox } from '@fancyapps/ui/dist/fancybox/';
import { Swiper, SwiperSlide } from 'swiper/react';
import { A11y, Keyboard } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import type { ResolvedSolutionScene } from '@/types/solutions';
import { enquiryStore, useEnquiry } from '@/lib/enquiry/store';
import { ENQUIRY_PATH, MAX_ENQUIRY_QUANTITY, validQuantity } from '@/lib/enquiry/domain';
import { productImages, productSpecifications } from '@/lib/catalog/product-detail';
import { formatPrice } from '@/lib/format-price';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import 'swiper/css';
import styles from './SolutionGallery.module.css';

export function SolutionGallery({ scenes, categoryId }: { scenes: ResolvedSolutionScene[]; categoryId: string }) {
  const [sceneId, setSceneId] = useState(scenes[0]?.id);
  const scene = scenes.find((entry) => entry.id === sceneId) ?? scenes[0];
  if (!scene) return null;
  return <div className={styles.gallery}>
    <div className={styles.sceneSelector} aria-label="Choose a scene">
      {scenes.map((entry) => {
        const cover = entry.images.find((image) => image.id === entry.coverImageId) ?? entry.images[0];
        return <button type="button" key={entry.id} aria-pressed={scene.id === entry.id} onClick={() => setSceneId(entry.id)}>
          {cover && <Image src={cover.src} alt="" width={90} height={60} sizes="90px" />}
          <span>{entry.title}<small>{entry.isConcept ? 'Concept visualisation' : 'Design inspiration'}</small></span>
        </button>;
      })}
    </div>
    <Scene key={scene.id} scene={scene} categoryId={categoryId} />
  </div>;
}

function Scene({ scene, categoryId }: { scene: ResolvedSolutionScene; categoryId: string }) {
  const [activeImage, setActiveImage] = useState(0);
  const [failedImages, setFailedImages] = useState<string[]>([]);
  const [activeItem, setActiveItem] = useState<string | null>(null);
  const [quantities, setQuantities] = useState<Record<string, string>>(() => Object.fromEntries(scene.items.map((item) => [item.id, String(item.recommendedQuantity)])));
  const [feedback, setFeedback] = useState('');
  const swiper = useRef<SwiperInstance | null>(null);
  const reducedMotion = useReducedMotion();
  const { hydrated } = useEnquiry();
  const available = scene.items.filter((item) => item.product && item.href);
  const isValid = (id: string) => quantities[id]?.trim() !== '' && validQuantity(Number(quantities[id]));
  const allValid = available.length > 0 && available.every((item) => isValid(item.id));

  function add(items: typeof scene.items) {
    try {
      const lines = items.flatMap((item) => item.product && item.href ? [{ productId: item.product.id, sku: item.product.sku, quantity: Number(quantities[item.id]) }] : []);
      if (!lines.length) return;
      const { persistent } = enquiryStore.addItems(lines);
      setFeedback(`Added to your enquiry: ${items.filter((item) => item.product && item.href).map((item) => `${quantities[item.id]} × ${item.product!.name} (SKU ${item.product!.sku})`).join('; ')}.${persistent ? '' : ' Browser storage is unavailable; keep this tab open to retain your selection.'}`);
    } catch (error) { setFeedback(error instanceof Error ? error.message : 'Unable to add these items. Please try again.'); }
  }

  function selectItem(id: string) {
    setActiveItem(id);
    if (window.matchMedia('(max-width: 767px)').matches) {
      document.getElementById(`${scene.id}-${id}`)?.scrollIntoView({ block: 'nearest', behavior: reducedMotion ? 'instant' : 'smooth' });
    }
  }

  const slide = (index: number) => {
    const image = scene.images[index];
    return <figure className={styles.figure}>
      <div className={styles.imageStage} style={{ aspectRatio: `${image.width} / ${image.height}` }}>
        {!failedImages.includes(image.id) && <Image src={image.src} alt={image.alt} fill sizes="(max-width: 767px) calc(100vw - 32px), (max-width: 1420px) 60vw, 840px" onError={() => setFailedImages((previous) => previous.includes(image.id) ? previous : [...previous, image.id])} />}
        <span className={styles.imageFallback}>Scene image unavailable. Explore the specification below.</span>
        {scene.hotspots.filter((hotspot) => hotspot.imageId === image.id && !failedImages.includes(image.id)).map((hotspot) => {
          const itemIndex = scene.items.findIndex((item) => item.id === hotspot.solutionItemId);
          const item = scene.items[itemIndex];
          return <button key={hotspot.id} type="button" className={styles.hotspot} style={{ left: `${hotspot.x * 100}%`, top: `${hotspot.y * 100}%` }}
            aria-label={`Marker ${itemIndex + 1}: ${item.product?.name}, ${item.matchType === 'exact' ? 'Featured product' : 'Suggested product'}${hotspot.label ? `. ${hotspot.label}` : ''}`}
            aria-pressed={activeItem === item.id} aria-controls={`${scene.id}-${item.id}`} tabIndex={index === activeImage ? 0 : -1}
            onClick={() => selectItem(item.id)}>{itemIndex + 1}</button>;
        })}
      </div>
      {image.caption && <figcaption>{image.caption}</figcaption>}
    </figure>;
  };

  return <div onKeyDown={(event) => { if (event.key === 'Escape') setActiveItem(null); }}>
    <div className={styles.sceneLayout}>
      <div className={styles.visual}>
        {!scene.images.length ? <p className={styles.empty}>Scene imagery is being prepared. Explore the specification below.</p>
          : scene.images.length === 1 ? slide(0)
            : <Swiper modules={[A11y, Keyboard]} keyboard={{ enabled: true, onlyInViewport: true }} speed={reducedMotion ? 0 : 250}
              noSwipingSelector="button, input, a" onSwiper={(instance) => { swiper.current = instance; }} onSlideChange={(instance) => { setActiveImage(instance.activeIndex); setActiveItem(null); }}>
              {scene.images.map((image, index) => <SwiperSlide key={image.id}>{slide(index)}</SwiperSlide>)}
            </Swiper>}
        {scene.images.length > 0 && !failedImages.includes(scene.images[activeImage].id) && <div className={styles.imageTools}>
          <span>Image {activeImage + 1} of {scene.images.length} · Select a numbered suggestion</span>
          <button type="button" onClick={() => Fancybox.show(scene.images.map((image) => ({ src: image.src, caption: `${scene.isConcept ? 'Concept visualisation. ' : ''}${image.alt}. Image-only view; product suggestions are listed on the page.` })), { startIndex: activeImage, placeFocusBack: true, fadeEffect: !reducedMotion, zoomEffect: !reducedMotion })}>View image only ↗</button>
        </div>}
        {scene.images.length > 1 && <div className={styles.imageSelector} aria-label="Choose scene image">
          {scene.images.map((image, index) => <button type="button" key={image.id} aria-label={`Show scene image ${index + 1}`} aria-pressed={index === activeImage} onClick={() => swiper.current?.slideTo(index)}><Image src={image.src} alt="" width={90} height={60} sizes="90px" /></button>)}
        </div>}
        <p className={styles.disclaimer}>{scene.isConcept ? 'Illustrative design concept. ' : ''}Suggested products are catalogue alternatives, not confirmed matches to the image. Planting and furniture are not included.</p>
      </div>
      <div className={styles.specification}>
        <h4>Suggested specification</h4>
        <p className={styles.note}>Explore the products associated with this concept, review their dimensions and finishes, and adjust quantities to suit your project.</p>
        {!scene.items.length && <p>Product suggestions are being prepared. <Link href="/shop">Explore Collections</Link></p>}
        <ol className={styles.items}>
          {scene.items.map((item, index) => {
            const product = item.product;
            const image = product && productImages(product)[0];
            const inputId = `${categoryId}-${item.id}-quantity`;
            const valid = isValid(item.id);
            return <li key={item.id} id={`${scene.id}-${item.id}`} data-active={activeItem === item.id}>
              <button type="button" className={styles.itemSelect} aria-pressed={activeItem === item.id} onClick={() => setActiveItem(item.id)}>
                <span className={styles.number}>{index + 1}</span><span>{product?.name ?? 'Product currently unavailable'}</span>
              </button>
              {product && item.href ? <>
                <div className={styles.productInfo}>
                  {image && <Image src={image.src} alt={image.alt} width={68} height={78} sizes="68px" />}
                  <div><span className={styles.match}>{item.matchType === 'exact' ? 'Featured product' : 'Suggested product'}</span><p>SKU {product.sku}</p>
                    {productSpecifications(product).map((row) => <p key={row.label}>{row.label}: {row.value}</p>)}
                    <p>{formatPrice(product.priceAed)}{product.priceAed !== null ? ' · illustrative catalogue price' : ''}</p>
                  </div>
                </div>
                {item.displayNote && <p className={styles.note}>{item.displayNote}</p>}
                <div className={styles.quantityRow}><label htmlFor={inputId}>Quantity</label><input id={inputId} type="number" inputMode="numeric" min={1} max={MAX_ENQUIRY_QUANTITY} step={1} value={quantities[item.id]} aria-invalid={!valid} aria-describedby={!valid ? `${inputId}-error` : undefined}
                  onChange={(event) => setQuantities((previous) => ({ ...previous, [item.id]: event.target.value }))} /></div>
                {!valid && <p id={`${inputId}-error`} className={styles.error}>Enter a whole number from 1 to {MAX_ENQUIRY_QUANTITY.toLocaleString('en-US')}.</p>}
                <div className={styles.itemActions}><Link href={item.href}>View Product ↗</Link><Button size="compact-sm" variant="outline" disabled={!hydrated || !valid} onClick={() => add([item])}>Add to Enquiry</Button></div>
              </> : <p className={styles.note}>This suggestion is unavailable. <Link href="/shop">Explore Collections</Link></p>}
            </li>;
          })}
        </ol>
        <Button fullWidth disabled={!hydrated || !allValid} onClick={() => add(available)}>{available.length === scene.items.length ? 'Add Solution to Enquiry' : 'Add Available to Enquiry'}</Button>
        <p className={styles.note}>Saves your selection for an enquiry. A quotation will be confirmed by our team.</p>
        <div role="status" aria-live="polite" aria-atomic="true" className={styles.feedback}>{feedback}</div>
        {feedback.startsWith('Added') && <Link href={ENQUIRY_PATH}>Review Enquiry →</Link>}
      </div>
    </div>
  </div>;
}
